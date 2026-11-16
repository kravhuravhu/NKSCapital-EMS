<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AuditService;
use App\Services\EmployeeNumberService;
use App\Models\LeaveConfig;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Mail;

class OfferService
{
    /**
     * Generate an offer for a candidate.
     */
    public function generateOffer(Candidate $candidate, array $data, User $actor): Offer
    {
        return DB::transaction(function () use ($candidate, $data, $actor) {
            if ($candidate->isRejected() || $candidate->isHired()) {
                throw new \RuntimeException("Cannot generate offer for a {$candidate->current_stage} candidate.");
            }

            // Withdraw any other pending offers for this candidate
            Offer::where('candidate_id', $candidate->id)
                ->where('status', 'extended')
                ->update([
                    'status' => 'withdrawn',
                    'withdrawn_at' => now(),
                    'withdrawn_by' => $actor->id,
                    'withdrawn_reason' => 'Superseded by new offer',
                ]);

            // Generate PDF
            $pdf = Pdf::loadView('pdf.offer-letter', [
                'candidate' => $candidate,
                'requisition' => $candidate->requisition,
                'salary' => $data['salary_offered'],
                'position' => $data['position'] ?? $candidate->requisition->title,
                'department' => $data['department'] ?? $candidate->requisition->department,
                'contract_type' => $data['contract_type'] ?? null,
                'start_date' => $data['start_date'],
                'expiry_date' => $data['expiry_date'],
                'benefits' => $data['benefits'] ?? [],
                'notice_period_days' => $data['notice_period_days'] ?? 30,
                'generated_at' => now()->format('d M Y H:i'),
                'company' => config('app.name', 'NKS Capital (Pty) Ltd'),
            ]);

            $filename = 'offer-' . $candidate->id . '-' . time() . '.pdf';
            $path = 'offers/' . $filename;
            $content = $pdf->output();
            Storage::disk('public')->put($path, $content);
            $hash = hash('sha256', $content);

            // Create offer
            $offer = Offer::create([
                'candidate_id' => $candidate->id,
                'offer_letter_path' => $path,
                'offer_letter_hash' => $hash,
                'salary_offered' => $data['salary_offered'],
                'position' => $data['position'] ?? $candidate->requisition->title,
                'department' => $data['department'] ?? $candidate->requisition->department,
                'contract_type' => $data['contract_type'] ?? null,
                'manager_id' => $data['manager_id'] ?? $candidate->requisition->hiring_manager_id,
                'notice_period_days' => $data['notice_period_days'] ?? 30,
                'benefits' => $data['benefits'] ?? [],
                'start_date' => $data['start_date'],
                'expiry_date' => $data['expiry_date'],
                'status' => 'extended',
                'created_by' => $actor->id,
            ]);

            // Advance candidate to offer stage
            if ($candidate->current_stage !== 'offer') {
                $candidate->current_stage = 'offer';
                $candidate->save();
            }

            AuditService::log(
                action: 'OFFER_GENERATED',
                tableName: 'offers',
                recordId: $offer->id,
                newValues: [
                    'candidate_id' => $candidate->id,
                    'salary_offered' => $offer->salary_offered,
                    'start_date' => $offer->start_date?->toDateString(),
                    'expiry_date' => $offer->expiry_date?->toDateString(),
                    'offer_letter_hash' => $hash,
                ],
                logType: 'success'
            );

            // Notify candidate's recruiter/manager
            $this->notifyStakeholders(
                $candidate,
                'OFFER_GENERATED',
                'Offer Generated',
                "Offer generated for {$candidate->full_name} ({$offer->position}). Salary: " . number_format($offer->salary_offered, 2) . " ZAR"
            );

            return $offer;
        });
    }

    /**
     * Accept an offer.
     */
    public function acceptOffer(Offer $offer, ?UploadedFile $signedCopy, User $actor): Offer
    {
        return DB::transaction(function () use ($offer, $signedCopy, $actor) {
            if (!$offer->canAccept()) {
                throw new \RuntimeException(
                    "Offer cannot be accepted in status {$offer->status}" .
                    ($offer->isExpired() ? ' (expired)' : '')
                );
            }

            // persist the signed copy if provided
            $signedHash = null;

            if ($signedCopy) {
                $filename = 'offer-signed-' . $offer->id . '-' . time() . '.' . $signedCopy->getClientOriginalExtension();
                $path = $signedCopy->storeAs('offers/signed', $filename, 'public');

                $signedHash = hash_file('sha256', $signedCopy->getRealPath());

                $offer->signed_copy_path = $path;
                $offer->signed_copy_hash = $signedHash;
            }

            $offer->status = 'accepted';
            $offer->accepted_at = now();
            $offer->save();

            AuditService::log(
                action: 'OFFER_ACCEPTED',
                tableName: 'offers',
                recordId: $offer->id,
                oldValues: ['status' => 'extended'],
                newValues: [
                    'status' => 'accepted',
                    'accepted_at' => now()->toIso8601String(),
                    'signed_copy_hash' => $signedHash,
                ],
                logType: 'success'
            );

            $this->notifyStakeholders(
                $offer->candidate,
                'OFFER_ACCEPTED',
                'Offer Accepted',
                "{$offer->candidate->full_name} has ACCEPTED the offer for {$offer->position}."
            );

            return $offer->fresh();
        });
    }

    /**
     * Decline an offer.
     */
    public function declineOffer(Offer $offer, string $reason, User $actor): Offer
    {
        return DB::transaction(function () use ($offer, $reason, $actor) {
            if (!$offer->canDecline()) {
                throw new \RuntimeException("Offer cannot be declined in status {$offer->status}");
            }

            $offer->status = 'declined';
            $offer->declined_at = now();
            $offer->declined_reason = $reason;
            $offer->save();

            // Update candidate stage
            $candidate = $offer->candidate;
            $candidate->current_stage = 'offer_declined';
            $candidate->save();

            AuditService::log(
                action: 'OFFER_DECLINED',
                tableName: 'offers',
                recordId: $offer->id,
                oldValues: ['status' => 'extended'],
                newValues: [
                    'status' => 'declined',
                    'reason' => $reason,
                    'declined_at' => now()->toIso8601String(),
                ],
                logType: 'warning'
            );

            $this->notifyStakeholders(
                $candidate,
                'OFFER_DECLINED',
                'Offer Declined',
                "{$candidate->full_name} has DECLINED the offer. Reason: {$reason}"
            );

            return $offer->fresh();
        });
    }

    /**
     * Withdraw an offer (recruiter-side).
     */
    public function withdrawOffer(Offer $offer, string $reason, User $actor): Offer
    {
        return DB::transaction(function () use ($offer, $reason, $actor) {
            if (!$offer->canWithdraw()) {
                throw new \RuntimeException("Offer cannot be withdrawn in status {$offer->status}");
            }

            $offer->status = 'withdrawn';
            $offer->withdrawn_at = now();
            $offer->withdrawn_by = $actor->id;
            $offer->withdrawn_reason = $reason;
            $offer->save();

            AuditService::log(
                action: 'OFFER_WITHDRAWN',
                tableName: 'offers',
                recordId: $offer->id,
                oldValues: ['status' => 'extended'],
                newValues: [
                    'status' => 'withdrawn',
                    'reason' => $reason,
                    'withdrawn_by' => $actor->id,
                ],
                logType: 'warning'
            );

            return $offer->fresh();
        });
    }

    /**
     * Send offer email to candidate.
     */
    public function sendOfferEmail(Offer $offer, User $actor, ?string $customMessage = null): Offer
    {
        return DB::transaction(function () use ($offer, $actor, $customMessage) {
            if ($offer->status !== 'extended') {
                throw new \RuntimeException("Can only send offers in 'extended' status.");
            }

            if (!$offer->offer_letter_path || !Storage::disk('public')->exists($offer->offer_letter_path)) {
                throw new \RuntimeException('Offer letter PDF is missing. Regenerate the offer first.');
            }

            $candidate = $offer->candidate;
            $email = $candidate->email;
            $subject = 'Offer of Employment — ' . ($offer->position ?? config('app.name'));

            $body = $customMessage
                ?? "Dear {$candidate->first_name},\n\n"
                . "Please find attached our offer of employment for the position of {$offer->position}.\n\n"
                . "This offer is valid until " . ($offer->expiry_date?->format('d F Y') ?? 'a date to be confirmed') . ".\n\n"
                . "Kind regards,\n"
                . config('app.name');

            try {
                Mail::raw($body, function ($mail) use ($email, $subject, $offer) {
                    $mail->to($email)
                        ->subject($subject)
                        ->attach(
                            Storage::disk('public')->path($offer->offer_letter_path),
                            ['as' => 'offer-letter.pdf', 'mime' => 'application/pdf']
                        );
                });

                $offer->sent_at = now();
                $offer->save();
            } catch (\Throwable $e) {
                AuditService::log(
                    action: 'OFFER_EMAIL_FAILED',
                    tableName: 'offers',
                    recordId: $offer->id,
                    newValues: ['error' => $e->getMessage(), 'email' => $email],
                    logType: 'error',
                    severity: 'error'
                );

                throw new \RuntimeException('Failed to send offer email: ' . $e->getMessage());
            }

            AuditService::log(
                action: 'OFFER_EMAIL_SENT',
                tableName: 'offers',
                recordId: $offer->id,
                newValues: [
                    'sent_to' => $email,
                    'sent_at' => now()->toIso8601String(),
                ],
                logType: 'success'
            );

            return $offer->fresh();
        });
    }

    /**
     * Convert an accepted candidate to an employee.
     */
    public function convertToEmployee(Offer $offer, array $data, User $actor): array
    {
        return DB::transaction(function () use ($offer, $data, $actor) {
            if (!$offer->isAccepted()) {
                throw new \RuntimeException('Only accepted offers can be converted.');
            }

            $candidate = $offer->candidate;

            if ($candidate->isHired()) {
                throw new \RuntimeException('Candidate has already been converted to an employee.');
            }

            $requisition = $candidate->requisition;

            $employeeType = $data['employee_type']
                ?? $requisition->employee_type_target
                ?? 'pr';

            $serviceType = $data['service_type']
                ?? $this->mapEmploymentTypeToServiceType($requisition->employment_type);

            // Generate employee number
            $employeeNumber = EmployeeNumberService::generate();

            // Generate a temporary password
            $tempPassword = Str::random(12);

            // pull default leave entitlements from config
            $annualConfig = LeaveConfig::where('leave_type', 'annual')->first();
            $sickConfig = LeaveConfig::where('leave_type', 'sick')->first();
            $familyConfig = LeaveConfig::where('leave_type', 'family')->first();

            $employee = User::create([
                'employee_number' => $employeeNumber,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'password' => Hash::make($tempPassword),
                'department' => $offer->department ?? $requisition->department,
                'position' => $offer->position ?? $requisition->title,
                'role' => $data['role'] ?? 'employee',
                'employee_type' => $employeeType,
                'service_type' => $serviceType,
                'manager_id' => $data['manager_id'] ?? $offer->manager_id ?? $requisition->hiring_manager_id,
                'client_id' => $data['client_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'hire_date' => $offer->start_date?->toDateString() ?? now()->toDateString(),
                'is_active' => true,
                'pending_company_email' => true,
                'leave_balance_annual' => (float) ($annualConfig?->default_entitlement ?? 0),
                'leave_balance_sick' => (float) ($sickConfig?->default_entitlement ?? 0),
                'leave_balance_family' => (float) ($familyConfig?->default_entitlement ?? 0),
            ]);

            $employee->assignRole($data['role'] ?? 'employee');

            // Draft employment contract pending signature
            Contract::create([
                'user_id' => $employee->id,
                'version' => 1,
                'file_path' => '',
                'effective_date' => $offer->start_date?->toDateString() ?? now()->toDateString(),
                'expiry_date' => null,
                'salary_annual' => $offer->salary_offered,
                'notice_period_days' => $offer->notice_period_days ?? 30,
                'position' => $offer->position ?? $requisition->title,
                'contract_type' => $this->mapOfferContractType($offer->contract_type),
                'status' => 'pending_signature',
                'uploaded_by' => $actor->id,
            ]);

            app(OnboardingService::class)->seed($employee);

            $candidate->hired_employee_id = $employee->id;
            $candidate->converted_from_offer_id = $offer->id;
            $candidate->conversion_notes = $data['notes'] ?? null;
            $candidate->current_stage = 'hired';
            $candidate->hire_date = $offer->start_date?->toDateString() ?? now()->toDateString();
            $candidate->save();

            AuditService::log(
                action: 'CANDIDATE_CONVERTED_TO_EMPLOYEE',
                tableName: 'users',
                recordId: $employee->id,
                newValues: [
                    'candidate_id' => $candidate->id,
                    'offer_id' => $offer->id,
                    'employee_number' => $employeeNumber,
                    'employee_type' => $employeeType,
                    'pending_company_email' => true,
                ],
                logType: 'success'
            );

            Notification::create([
                'user_id' => $employee->id,
                'type' => 'WELCOME_EMPLOYEE',
                'title' => 'Welcome to ' . config('app.name'),
                'message' => "Welcome aboard! Your employee number is {$employeeNumber}. IT will contact you with your company email.",
                'reference_id' => $employee->id,
                'reference_type' => User::class,
            ]);

            if ($employee->manager_id) {
                Notification::create([
                    'user_id' => $employee->manager_id,
                    'type' => 'TEAM_MEMBER_ADDED',
                    'title' => 'New Team Member',
                    'message' => "{$employee->full_name} has been onboarded as {$employee->position}.",
                    'reference_id' => $employee->id,
                    'reference_type' => User::class,
                ]);
            }

            // Welcome email to personal address. Login credentials are NOT sent.
            // An administrator will provision a company email separately and the
            // credentials will be delivered at that time through the support console.
            try {
                Mail::raw(
                    "Dear {$employee->first_name},\n\n"
                    . "Welcome to " . config('app.name') . "!\n\n"
                    . "Your employee number is: {$employeeNumber}\n\n"
                    . "Your account is being prepared. Our IT team will contact you shortly with your company email address and login credentials.\n\n"
                    . "If you have questions in the meantime, reply to this email and your onboarding manager will assist.\n\n"
                    . "Kind regards,\n"
                    . config('app.name'),
                    function ($mail) use ($employee) {
                        $mail->to($employee->email)
                            ->subject('Welcome to ' . config('app.name'));
                    }
                );

                AuditService::log(
                    action: 'WELCOME_EMAIL_SENT',
                    tableName: 'users',
                    recordId: $employee->id,
                    newValues: ['email' => $employee->email, 'contains_credentials' => false],
                    logType: 'success'
                );
            } catch (\Throwable $e) {
                AuditService::log(
                    action: 'WELCOME_EMAIL_FAILED',
                    tableName: 'users',
                    recordId: $employee->id,
                    newValues: ['email' => $employee->email, 'error' => $e->getMessage()],
                    logType: 'error',
                    severity: 'error'
                );
            }

            return [
                'employee' => $employee,
                'temporary_password' => $tempPassword,
            ];
        });
    }

    protected function mapOfferContractType(?string $type): string
    {
        return match ($type) {
            'permanent' => 'permanent',
            'fixed_term', 'contract' => 'fixed_term',
            'consulting' => 'consulting',
            'internship' => 'internship',
            default => 'permanent',
        };
    }

    /**
     * Auto-expire stale offers.
     */
    public function autoExpireOffers(): int
    {
        $stale = Offer::where('status', 'extended')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($stale as $offer) {
            $offer->status = 'expired';
            $offer->save();

            AuditService::log(
                action: 'OFFER_AUTO_EXPIRED',
                tableName: 'offers',
                recordId: $offer->id,
                oldValues: ['status' => 'extended'],
                newValues: [
                    'status' => 'expired',
                    'expiry_date' => $offer->expiry_date?->toDateString(),
                ],
                logType: 'warning'
            );

            $count++;
        }
        return $count;
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    protected function notifyStakeholders(Candidate $candidate, string $type, string $title, string $message): void
    {
        $recipients = collect();

        $requisition = $candidate->requisition;
        if ($requisition?->created_by) {
            $recipients->push($requisition->created_by);
        }
        if ($requisition?->hiring_manager_id) {
            $recipients->push($requisition->hiring_manager_id);
        }

        // All recruiters + admins
        $staff = User::whereIn('role', ['recruiter', 'admin', 'super_admin'])
            ->where('is_active', true)
            ->pluck('id');

        $recipients = $recipients->merge($staff)->unique('id');

        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => is_object($recipient) ? $recipient->id : $recipient,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'reference_id' => $candidate->id,
                'reference_type' => Candidate::class,
            ]);
        }
    }

    protected function mapEmploymentTypeToServiceType(string $employmentType): string
    {
        return match ($employmentType) {
            'full_time' => 'permanent',
            'part_time' => 'temporary',
            'contract' => 'contractor',
            default => 'permanent',
        };
    }
}