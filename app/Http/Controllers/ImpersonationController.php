<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\PersonalAccessToken;

class ImpersonationController extends Controller
{
    public function stop(Request $request)
    {
        $adminId = Session::get('impersonation_admin_user_id');
        $adminToken = Session::get('impersonation_admin_token');

        if (! $adminId || ! $adminToken) {
            return redirect()->route('portal.dashboard');
        }

        $currentToken = Session::get('api_token');

        if ($currentToken) {
            PersonalAccessToken::findToken($currentToken)?->delete();
        }

        Session::forget('impersonated_by_user_id');
        Session::forget('impersonation_admin_user_id');
        Session::forget('impersonation_admin_token');
        Session::put('api_token', $adminToken);

        $admin = User::find($adminId);

        if ($admin) {
            Auth::login($admin);

            AuditService::log(
                action: 'SUPPORT_IMPERSONATE_STOPPED',
                tableName: 'users',
                recordId: $admin->id,
                newValues: ['restored_admin_id' => $admin->id],
                logType: 'success'
            );
        }

        return redirect()->route('portal.dashboard');
    }
}