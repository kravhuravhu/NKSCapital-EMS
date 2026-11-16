<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmployeeOnboardingItem;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OnboardingService;
use App\Support\RoleCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    public function __construct(protected OnboardingService $onboarding) {}

    public function show($userId)
    {
        $actor = Auth::user();

        if (! RoleCheck::has($actor, ['admin', 'super_admin', 'director', 'operational_manager', 'manager'])
            && (int) $actor->id !== (int) $userId) {
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        $user = User::findOrFail($userId);
        $this->onboarding->seed($user);

        $items = EmployeeOnboardingItem::where('user_id', $user->id)
            ->with('completedBy:id,first_name,last_name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->full_name,
                'employee_number' => $user->employee_number,
                'progress' => $this->onboarding->progress($user),
                'pending_company_email' => (bool) $user->pending_company_email,
                'items' => $items,
            ],
        ], 200);
    }

    public function update(Request $request, $userId)
    {
        $actor = Auth::user();

        if (! RoleCheck::has($actor, ['admin', 'super_admin', 'director', 'operational_manager', 'manager'])) {
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        $validator = Validator::make($request->all(), [
            'item_key' => 'required|string|max:50',
            'status' => 'required|in:pending,in_progress,completed,not_applicable',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($userId);

        $item = $this->onboarding->updateStatus(
            $user,
            $request->item_key,
            $request->status,
            $request->notes,
            $actor
        );

        AuditService::log(
            action: 'ONBOARDING_ITEM_UPDATED',
            tableName: 'employee_onboarding_items',
            recordId: $item->id,
            newValues: [
                'user_id' => $user->id,
                'item_key' => $item->item_key,
                'status' => $item->status,
            ],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Onboarding item updated',
            'data' => $item->fresh('completedBy'),
        ], 200);
    }
}