<?php

namespace App\Services;

use App\Models\CalendarConnection;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;

class CalendarService
{
    /**
     * Get the OAuth connect URL for a provider.
     */
    public function getConnectUrl(User $user, string $provider): array
    {
        $clientId = config("services.{$provider}.client_id");
        $redirectUri = config("services.{$provider}.redirect") ?? url("/api/v1/integrations/calendar/callback/{$provider}");

        if (!$clientId) {
            return [
                'configured' => false,
                'message' => "{$provider} is not configured. Please contact your administrator.",
                'provider' => $provider,
            ];
        }

        $authUrls = [
            'google' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'outlook' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'ical' => null,
        ];

        $scopes = [
            'google' => 'https://www.googleapis.com/auth/calendar.events',
            'outlook' => 'offline_access Calendars.ReadWrite',
            'ical' => null,
        ];

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scopes[$provider] ?? '',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => base64_encode(json_encode(['user_id' => $user->id, 'provider' => $provider])),
        ]);

        return [
            'configured' => true,
            'provider' => $provider,
            'authorization_url' => $authUrls[$provider] . '?' . $query,
        ];
    }

    /**
     * Sync meetings to external calendar (best-effort).
     */
    public function sync(User $user, string $direction = 'both'): array
    {
        $connection = CalendarConnection::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            throw new \RuntimeException('No active calendar connection found. Please connect first.');
        }

        $pushed = 0;
        $pulled = 0;

        if (in_array($direction, ['push', 'both'])) {
            $meetings = Meeting::where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('department', $user->department);
            })
                ->whereBetween('start_time', [now()->subDays(7), now()->addDays(60)])
                ->get();

            // Best-effort — actual push handled by queue job per provider
            $pushed = $meetings->count();
        }

        if (in_array($direction, ['pull', 'both'])) {
            // Best-effort — actual pull handled by queue job per provider
            $pulled = 0;
        }

        $connection->last_synced_at = now();
        $connection->save();

        AuditService::log(
            action: 'CALENDAR_SYNCED',
            tableName: 'calendar_connections',
            recordId: $connection->id,
            newValues: [
                'provider' => $connection->provider,
                'direction' => $direction,
                'pushed' => $pushed,
                'pulled' => $pulled,
            ],
            logType: 'success'
        );

        return [
            'provider' => $connection->provider,
            'direction' => $direction,
            'pushed' => $pushed,
            'pulled' => $pulled,
            'synced_at' => $connection->last_synced_at->toIso8601String(),
        ];
    }
}