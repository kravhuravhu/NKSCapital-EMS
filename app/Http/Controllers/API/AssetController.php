<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCustodyHistory;
use App\Models\User;
use App\Services\AssetService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AssetController extends Controller
{
    protected AssetService $assetService;

    public function __construct(AssetService $assetService)
    {
        $this->assetService = $assetService;
    }

    /**
     * POST /api/v1/asset/register
     */
    public function register(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'asset_tag' => 'required|string|unique:assets,asset_tag',
            'serial_number' => 'nullable|string',
            'model' => 'required|string',
            'manufacturer' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'condition' => 'nullable|in:new,good,fair,poor,damaged,obsolete,refurbished,for_repair',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $asset = $this->assetService->registerAsset($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Asset registered successfully',
            'data' => $asset
        ], 201);
    }

    /**
     * POST /api/v1/asset/loan
     */
    public function loan(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'assigned_to_id' => 'required|exists:users,id',
            'checkout_date' => 'nullable|date',
            'expected_return_date' => 'nullable|date|after_or_equal:checkout_date',
            'checkout_condition' => 'nullable|string',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $asset = Asset::findOrFail($request->asset_id);
        $assignee = User::findOrFail($request->assigned_to_id);

        try {
            $history = $this->assetService->loanAsset($asset, $assignee, $request->all(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Asset loaned successfully',
            'data' => [
                'asset' => $asset->fresh(),
                'custody_history' => $history,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/asset/return
     */
    public function returnAsset(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'actual_return_date' => 'nullable|date',
            'return_condition' => 'required|in:new,good,fair,poor,damaged,obsolete,refurbished,for_repair',
            'damage_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'repair_priority' => 'nullable|in:low,medium,high,critical',
            'notes' => 'nullable|string',
            'returned_to_location' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $asset = Asset::findOrFail($request->asset_id);

        $payload = $request->all();
        if ($request->hasFile('damage_photo')) {
            $file = $request->file('damage_photo');
            $filename = 'asset-damage-' . $asset->id . '-' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('asset-damage-photos', $filename, 'public');
            $payload['damage_photo_path'] = $path;
            $payload['damage_photo_hash'] = hash_file('sha256', $file->getRealPath());
        }

        try {
            $history = $this->assetService->returnAsset($asset, $payload, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => $asset->fresh()->status === 'repair_requested'
                ? 'Asset returned — flagged for repair'
                : 'Asset returned successfully',
            'data' => [
                'asset' => $asset->fresh(),
                'custody_history' => $history,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/asset/overdue
     */
    public function overdue()
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $assets = Asset::with(['currentAssignee:id,first_name,last_name,employee_number,email'])
            ->overdue()
            ->get()
            ->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'model' => $asset->model,
                    'status' => $asset->status,
                    'current_assignee' => $asset->currentAssignee,
                    'days_overdue' => $asset->daysOverdue(),
                    'expected_return_date' => $asset->activeLoan->first()?->expected_return_date?->toDateString(),
                    'overdue_notification_level' => $asset->overdue_notification_level,
                    'last_overdue_notified_at' => $asset->last_overdue_notified_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'count' => $assets->count(),
                'overdue_assets' => $assets,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/asset/track
     * Dashboard of all loaned assets.
     */
    public function track(Request $request)
    {
        $user = Auth::user();

        $query = Asset::with(['currentAssignee:id,first_name,last_name,employee_number,email,department'])
            ->where('status', 'loaned');

        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            // Employees see only their own loaned assets
            $query->where('current_assignee_id', $user->id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('asset_tag', 'like', "%{$s}%")
                  ->orWhere('serial_number', 'like', "%{$s}%")
                  ->orWhere('model', 'like', "%{$s}%")
                  ->orWhereHas('currentAssignee', function ($qq) use ($s) {
                      $qq->where('first_name', 'like', "%{$s}%")
                         ->orWhere('last_name', 'like', "%{$s}%")
                         ->orWhere('employee_number', 'like', "%{$s}%");
                  });
            });
        }

        $assets = $query->get()->map(function ($asset) {
            $days = $asset->daysOverdue();
            return [
                'id' => $asset->id,
                'asset_tag' => $asset->asset_tag,
                'model' => $asset->model,
                'serial_number' => $asset->serial_number,
                'condition' => $asset->condition,
                'status' => $asset->status,
                'current_assignee' => $asset->currentAssignee,
                'expected_return_date' => $asset->activeLoan->first()?->expected_return_date?->toDateString(),
                'days_overdue' => $days > 0 ? $days : 0,
                'is_overdue' => $days > 0,
                'overdue_notification_level' => $asset->overdue_notification_level,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'count' => $assets->count(),
                'loaned_assets' => $assets,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/asset/repair/request
     */
    public function requestRepair(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'issue_description' => 'required|string|max:1000',
            'urgency' => 'nullable|in:low,medium,high,critical',
            'damage_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $asset = Asset::findOrFail($request->asset_id);

        // If employee, must own the asset
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            if ($asset->current_assignee_id !== $user->id) {
                return response()->json(['status' => 'error', 'message' => 'You can only request repair for assets assigned to you.'], 403);
            }
        }

        $payload = $request->all();
        if ($request->hasFile('damage_photo')) {
            $file = $request->file('damage_photo');
            $filename = 'asset-repair-' . $asset->id . '-' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('asset-damage-photos', $filename, 'public');
            $payload['damage_photo_path'] = $path;
            $payload['damage_photo_hash'] = hash_file('sha256', $file->getRealPath());
        }

        $asset = $this->assetService->requestRepair($asset, $payload, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Repair request submitted',
            'data' => $asset
        ], 200);
    }

    /**
     * GET /api/v1/asset/history/{assetId}
     */
    public function history($assetId)
    {
        $user = Auth::user();
        $asset = Asset::with([
            'custodyHistory.assignedTo:id,first_name,last_name,employee_number,email',
            'custodyHistory.creator:id,first_name,last_name',
        ])->findOrFail($assetId);

        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])
            && $asset->current_assignee_id !== $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'asset' => $asset->only([
                    'id', 'asset_tag', 'serial_number', 'model', 'manufacturer',
                    'condition', 'status', 'location', 'purchase_date', 'warranty_expiry',
                ]),
                'custody_history' => $asset->custodyHistory,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/asset/report
     */
    public function report(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'format' => 'nullable|in:json,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $stats = [
            'total' => Asset::count(),
            'available' => Asset::where('status', 'available')->count(),
            'loaned' => Asset::where('status', 'loaned')->count(),
            'in_repair' => Asset::whereIn('status', ['repair_requested', 'maintenance'])->count(),
            'retired' => Asset::where('status', 'retired')->count(),
            'lost' => Asset::where('status', 'lost')->count(),
            'overdue' => Asset::overdue()->count(),
            'total_value' => (float) Asset::sum('purchase_price'),
        ];

        $byCondition = Asset::selectRaw('`condition`, COUNT(*) as count')
            ->groupBy('condition')
            ->pluck('count', 'condition');

        $data = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'stats' => $stats,
            'by_condition' => $byCondition,
            'overdue_assets' => Asset::with('currentAssignee:id,first_name,last_name,employee_number')
                ->overdue()
                ->get()
                ->map(fn ($a) => [
                    'asset_tag' => $a->asset_tag,
                    'model' => $a->model,
                    'assignee' => $a->currentAssignee?->full_name,
                    'days_overdue' => $a->daysOverdue(),
                ]),
        ];

        AuditService::log(
            action: 'ASSET_REPORT_GENERATED',
            tableName: 'assets',
            recordId: 0,
            newValues: ['format' => $request->format ?? 'json'],
            logType: 'success'
        );

        if ($request->format === 'csv') {
            $csv = "Category,Value\n";
            foreach ($stats as $k => $v) {
                $csv .= "{$k},{$v}\n";
            }
            $path = 'reports/asset-report-' . time() . '.csv';
            Storage::disk('public')->put($path, $csv);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'download_url' => Storage::url($path),
                    'filename' => basename($path),
                ]
            ], 200);
        }

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }
}