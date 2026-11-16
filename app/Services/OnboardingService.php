<?php

namespace App\Services;

use App\Models\EmployeeOnboardingItem;
use App\Models\User;

class OnboardingService
{
    public const ITEMS = [
        'bank_account' => 'Bank account details',
        'tax_number' => 'Tax number',
        'uif_registration' => 'UIF registration',
        'pension_fund' => 'Pension fund enrollment',
        'medical_aid' => 'Medical aid enrollment',
        'emergency_contacts' => 'Emergency contacts verified',
        'id_documents' => 'ID documents uploaded',
    ];

    /**
     * Seed the default checklist. Idempotent, safe to call on existing users.
     */
    public function seed(User $user): void
    {
        foreach (self::ITEMS as $key => $label) {
            EmployeeOnboardingItem::firstOrCreate(
                ['user_id' => $user->id, 'item_key' => $key],
                ['label' => $label, 'status' => 'pending']
            );
        }
    }

    public function updateStatus(User $user, string $itemKey, string $status, ?string $notes, User $actor): EmployeeOnboardingItem
    {
        $item = EmployeeOnboardingItem::where('user_id', $user->id)
            ->where('item_key', $itemKey)
            ->firstOrFail();

        $item->status = $status;
        $item->notes = $notes;

        if ($status === 'completed') {
            $item->completed_by = $actor->id;
            $item->completed_at = now();
        } else {
            $item->completed_by = null;
            $item->completed_at = null;
        }

        $item->save();

        return $item;
    }

    /**
     * Progress 0 to 100, "not_applicable" items excluded from the denominator.
     */
    public function progress(User $user): int
    {
        $items = EmployeeOnboardingItem::where('user_id', $user->id)->get();

        $denominator = $items->where('status', '!=', 'not_applicable')->count();

        if ($denominator === 0) {
            return 0;
        }

        return (int) round(($items->where('status', 'completed')->count() / $denominator) * 100);
    }
}