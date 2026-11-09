<?php

namespace App\Support;

class AuditActionVerb
{
    public static function verb(string $action): string
    {
        $action = strtoupper($action);

        return match (true) {
            str_contains($action, 'LOGIN') => 'signed in',
            str_contains($action, 'LOGOUT') => 'signed out',
            str_contains($action, 'L2_APPROVED'),
            str_contains($action, 'L2_REJECTED') => str_contains($action, 'REJECTED') ? 'rejected L2' : 'approved L2',
            str_contains($action, 'L1_APPROVED'),
            str_contains($action, 'L1_REJECTED') => str_contains($action, 'REJECTED') ? 'rejected L1' : 'approved L1',
            str_contains($action, 'APPROVED'), str_contains($action, 'ACCEPTED'), str_contains($action, 'ACKNOWLEDGED') => 'approved',
            str_contains($action, 'REJECTED'), str_contains($action, 'DECLINED') => 'rejected',
            str_contains($action, 'CANCELLED'), str_contains($action, 'WITHDRAWN') => 'cancelled',
            str_contains($action, 'DELETED'), str_contains($action, 'REMOVED'), str_contains($action, 'FORGOTTEN'), str_contains($action, 'FLUSHED') => 'deleted',
            str_contains($action, 'CREATED'), str_contains($action, 'APPLIED'), str_contains($action, 'ADDED'), str_contains($action, 'REGISTERED'), str_contains($action, 'UPLOADED') => 'created',
            str_contains($action, 'UPDATED'), str_contains($action, 'CHANGED'), str_contains($action, 'EDITED'), str_contains($action, 'RENEWED') => 'updated',
            str_contains($action, 'VIEWED'), str_contains($action, 'DOWNLOADED'), str_contains($action, 'EXPORTED') => 'viewed',
            str_contains($action, 'SUBMITTED') => 'submitted',
            str_contains($action, 'RETURNED') => 'returned',
            str_contains($action, 'LOANED'), str_contains($action, 'ASSIGNED') => 'assigned',
            str_contains($action, 'AI_') => 'used AI on',
            str_contains($action, 'SEARCH') => 'searched',
            str_contains($action, 'VERIFIED') => 'verified',
            str_contains($action, 'SIGNED') => 'signed',
            default => strtolower(str_replace('_', ' ', $action)),
        };
    }

    public static function tone(string $action): string
    {
        $action = strtoupper($action);

        return match (true) {
            str_contains($action, 'DELETED'), str_contains($action, 'REJECTED'),
            str_contains($action, 'DECLINED'), str_contains($action, 'FORGOTTEN'),
            str_contains($action, 'FLUSHED') => 'danger',
            str_contains($action, 'APPROVED'), str_contains($action, 'ACCEPTED'),
            str_contains($action, 'CREATED'), str_contains($action, 'APPLIED'),
            str_contains($action, 'ACKNOWLEDGED') => 'success',
            str_contains($action, 'UPDATED'), str_contains($action, 'SUBMITTED'),
            str_contains($action, 'RENEWED') => 'info',
            default => 'muted',
        };
    }
}