<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send a notification to a user (respecting preferences).
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        string $category = 'general',
        string $priority = 'normal',
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?string $groupKey = null,
        bool $force = false
    ): ?Notification {
        $pref = NotificationPreference::resolveFor($user, $category);

        // Skip if user has fully opted out (unless forced)
        if (!$force && !$pref->in_app && !$pref->email) {
            return null;
        }

        $notification = null;

        if ($pref->in_app || $force) {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'channel' => $pref->email ? 'both' : 'in_app',
                'category' => $category,
                'priority' => $priority,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'action_label' => $actionLabel,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'group_key' => $groupKey,
                'is_read' => false,
                'delivered_at' => now(),
                'expires_at' => now()->addDays(90),
            ]);
        }

        if ($pref->email && !$pref->digest_only) {
            $this->sendEmail($user, $title, $message, $actionUrl, $notification);
        }

        return $notification;
    }

    /**
     * Send a bulk notification to many users.
     */
    public function broadcast(
        array $userIds,
        string $type,
        string $title,
        string $message,
        string $category = 'general',
        string $priority = 'normal',
        ?string $actionUrl = null,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): int {
        $sent = 0;
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (!$user || !$user->is_active) continue;
            if ($this->send($user, $type, $title, $message, $category, $priority, $actionUrl, null, $referenceId, $referenceType)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Mark notification as read.
     */
    public function markRead(Notification $notification, User $user): Notification
    {
        if ($notification->user_id !== $user->id) {
            throw new \RuntimeException('Cannot mark another user\'s notification as read.');
        }
        $notification->markAsRead();
        return $notification->fresh();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Delete a notification.
     */
    public function delete(Notification $notification, User $user): bool
    {
        if ($notification->user_id !== $user->id) {
            throw new \RuntimeException('Cannot delete another user\'s notification.');
        }
        return (bool) $notification->delete();
    }

    /**
     * Send email (best effort — never breaks the caller).
     */
    protected function sendEmail(
        User $user,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?Notification $notification = null
    ): void {
        try {
            // Using raw inline for now; can swap for a Mailable
            Mail::raw(
                strip_tags("{$title}\n\n{$message}\n\n" . ($actionUrl ? "Action: {$actionUrl}" : '')),
                function ($mail) use ($user, $title) {
                    $mail->to($user->email)
                        ->subject('[NKS EMS] ' . $title);
                }
            );

            if ($notification) {
                $notification->sent_at = now();
                $notification->save();
            }
        } catch (\Throwable $e) {
            if ($notification) {
                $notification->failed_at = now();
                $notification->failure_reason = $e->getMessage();
                $notification->save();
            }

            AuditService::log(
                action: 'NOTIFICATION_EMAIL_FAILED',
                tableName: 'notifications',
                recordId: $notification?->id ?? 0,
                newValues: [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ],
                logType: 'error',
                severity: 'error'
            );
        }
    }
}