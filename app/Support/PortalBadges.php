<?php

namespace App\Support;

use App\Models\User;

class PortalBadges
{
    public static function for(User $user): array
    {
        $response = Api::get('/dashboard/pending-counts');

        if (!$response['ok'] || !is_array($response['data'])) {
            return [];
        }

        $data = $response['data'];

        return [
            'l1_pending'    => $data['l1_approvals']         ?? 0,
            'l2_pending'    => $data['l2_approvals']         ?? 0,
            'leave_pending' => $data['team_leave_requests']  ?? 0,
            'contract'      => $data['contract_expiring']    ?? 0,
            'overdue'       => $data['overdue_assets']       ?? 0,
        ];
    }
}