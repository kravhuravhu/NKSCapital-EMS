<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientManager;
use App\Models\ClientTemplate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    /**
     * GET /api/v1/client/list
     * List all active clients
     */
    public function listClients(Request $request)
    {
        $query = Client::query();

        // Filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('company_name', 'LIKE', "%{$search}%")
                  ->orWhere('registration_number', 'LIKE', "%{$search}%")
                  ->orWhere('primary_contact_name', 'LIKE', "%{$search}%")
                  ->orWhere('primary_contact_email', 'LIKE', "%{$search}%");
            });
        }

        // Load relationships
        $query->with(['managers', 'projects']);
        $query->withCount('projects', 'users');

        $clients = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data' => $clients
        ], 200);
    }

    /**
     * POST /api/v1/client/create
     * Create new client (Admin/Manager)
     */
    public function createClient(Request $request)
    {
        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to create clients'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:clients,registration_number',
            'vat_number' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'primary_contact_name' => 'required|string|max:255',
            'primary_contact_email' => 'required|email',
            'primary_contact_phone' => 'required|string',
            'contract_value' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $client = Client::create([
            'company_name' => $request->company_name,
            'registration_number' => $request->registration_number,
            'vat_number' => $request->vat_number,
            'billing_address' => $request->billing_address,
            'primary_contact_name' => $request->primary_contact_name,
            'primary_contact_email' => $request->primary_contact_email,
            'primary_contact_phone' => $request->primary_contact_phone,
            'contract_value' => $request->contract_value,
            'payment_terms' => $request->payment_terms,
            'status' => 'active',
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Client created successfully',
            'data' => $client
        ], 201);
    }

    /**
     * PUT /api/v1/client/update/{id}
     * Update client details
     */
    public function updateClient(Request $request, $id)
    {
        $client = Client::findOrFail($id);

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update clients'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'company_name' => 'sometimes|string|max:255',
            'registration_number' => 'sometimes|string|unique:clients,registration_number,' . $id,
            'vat_number' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'primary_contact_name' => 'sometimes|string|max:255',
            'primary_contact_email' => 'sometimes|email',
            'primary_contact_phone' => 'sometimes|string',
            'contract_value' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,suspended',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $client->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Client updated successfully',
            'data' => $client
        ], 200);
    }

    /**
     * POST /api/v1/client/deactivate/{id}
     * Deactivate client
     */
    public function deactivateClient($id)
    {
        $client = Client::findOrFail($id);

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to deactivate clients'
            ], 403);
        }

        $client->is_active = false;
        $client->status = 'inactive';
        $client->save();

        // Archive associated projects
        Project::where('client_id', $id)->update(['status' => 'archived']);

        return response()->json([
            'status' => 'success',
            'message' => 'Client deactivated successfully',
            'data' => [
                'client_id' => $client->id,
                'company_name' => $client->company_name,
                'is_active' => $client->is_active,
                'status' => $client->status,
                'archived_projects' => Project::where('client_id', $id)->count(),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/client/manager/add
     * Add external client manager
     */
    public function addClientManager(Request $request)
    {
        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to add client managers'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // If setting as primary, unset other primary managers
        if ($request->is_primary) {
            ClientManager::where('client_id', $request->client_id)
                ->update(['is_primary' => false]);
        }

        $manager = ClientManager::create([
            'client_id' => $request->client_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'is_primary' => $request->is_primary ?? false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Client manager added successfully',
            'data' => $manager
        ], 201);
    }

    /**
     * GET /api/v1/client/manager/list/{clientId}
     * List all managers for a client
     */
    public function listClientManagers($clientId)
    {
        $client = Client::findOrFail($clientId);
        $managers = ClientManager::where('client_id', $clientId)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'client_id' => $client->id,
                'company_name' => $client->company_name,
                'managers' => $managers,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/client/template/upload
     * Upload client-branded timesheet template
     */
    public function uploadClientTemplate(Request $request)
    {
        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to upload templates'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'template_name' => 'required|string|max:255',
            'template_file' => 'required|file|mimes:pdf,doc,docx|max:10240', // 10MB
            'branding_settings' => 'nullable|json',
            'template_content' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Store file
        $file = $request->file('template_file');
        $filename = 'client-' . $request->client_id . '-template-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('client-templates', $filename, 'public');
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Deactivate existing active templates for this client
        ClientTemplate::where('client_id', $request->client_id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Create new template
        $template = ClientTemplate::create([
            'client_id' => $request->client_id,
            'template_name' => $request->template_name,
            'template_content' => $request->template_content,
            'branding_settings' => $request->branding_settings ? json_decode($request->branding_settings, true) : null,
            'file_path' => $path,
            'file_hash' => $fileHash,
            'is_active' => true,
            'updated_by' => Auth::id(),
        ]);

        // Update client's template_id
        $client = Client::find($request->client_id);
        $client->template_id = $template->id;
        $client->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Client template uploaded successfully',
            'data' => $template
        ], 201);
    }

    /**
     * GET /api/v1/client/template/{clientId}
     * Get client template
     */
    public function getClientTemplate($clientId)
    {
        $client = Client::findOrFail($clientId);
        $template = ClientTemplate::where('client_id', $clientId)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active template found for this client'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'client_id' => $client->id,
                'company_name' => $client->company_name,
                'template' => $template,
                'download_url' => Storage::url($template->file_path),
            ]
        ], 200);
    }

    /**
     * PUT /api/v1/client/template/update/{id}
     * Update client template
     */
    public function updateClientTemplate(Request $request, $id)
    {
        $template = ClientTemplate::findOrFail($id);

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update templates'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'template_name' => 'sometimes|string|max:255',
            'branding_settings' => 'nullable|json',
            'template_content' => 'nullable|string',
            'template_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update file if provided
        if ($request->hasFile('template_file')) {
            // Delete old file
            if ($template->file_path) {
                Storage::disk('public')->delete($template->file_path);
            }
            
            $file = $request->file('template_file');
            $filename = 'client-' . $template->client_id . '-template-' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('client-templates', $filename, 'public');
            
            $template->file_path = $path;
            $template->file_hash = hash_file('sha256', $file->getRealPath());
        }

        if ($request->has('template_name')) {
            $template->template_name = $request->template_name;
        }
        if ($request->has('branding_settings')) {
            $template->branding_settings = json_decode($request->branding_settings, true);
        }
        if ($request->has('template_content')) {
            $template->template_content = $request->template_content;
        }

        $template->updated_by = Auth::id();
        $template->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Client template updated successfully',
            'data' => $template
        ], 200);
    }
}