<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Livewire\Component;

class Pipeline extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $requisitionFilter = '';
    public string $search = '';

    public array $requisitions = [];
    public array $columns = [];   // stage => [candidates]
    public int $total = 0;
    public bool $truncated = false;

    public bool $showView = false;
    public ?array $viewData = null;

    public bool $showReject = false;
    public ?int $rejectId = null;
    public string $rejectName = '';
    public string $rejectReason = '';

    // canonical column order for the board
    public array $stageOrder = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

    public array $stageLabels = [
        'applied'   => 'Applied',
        'screening' => 'Screening',
        'interview' => 'Interview',
        'offer'     => 'Offer',
        'hired'     => 'Hired',
        'rejected'  => 'Rejected',
    ];

    // linear progression (rejected is out-of-band)
    public array $linearFlow = ['applied', 'screening', 'interview', 'offer', 'hired'];

    public function mount(): void
    {
        $this->loadRequisitions();
        $this->load();
    }

    protected function loadRequisitions(): void
    {
        $response = Api::get('/recruitment/requisition/list', ['per_page' => 200]);

        if ($response['ok']) {
            $this->requisitions = $response['data']['data'] ?? [];
        }
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 200];

        if ($this->requisitionFilter !== '') {
            $params['requisition_id'] = $this->requisitionFilter;
        }
        if ($this->search !== '') {
            $params['search'] = $this->search;
        }

        $response = Api::get('/recruitment/candidate/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load pipeline.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $rows = $paginator['data'] ?? [];
        $this->total = $paginator['total'] ?? 0;
        $this->truncated = $this->total > count($rows);

        // bucket candidates into stage columns
        $columns = [];
        foreach ($this->stageOrder as $stage) {
            $columns[$stage] = [];
        }

        foreach ($rows as $row) {
            $stage = $row['current_stage'] ?? 'applied';
            if (!isset($columns[$stage])) {
                $columns[$stage] = [];
            }
            $columns[$stage][] = $row;
        }

        $this->columns = $columns;
        $this->loading = false;
    }

    public function refreshBoard(): void
    {
        $this->load();
    }

    public function applyFilters(): void
    {
        $this->load();
    }

    public function resetFilters(): void
    {
        $this->requisitionFilter = '';
        $this->search = '';
        $this->load();
    }

    // ---- drag-and-drop target ----

    public function moveCard($candidateId, string $newStage): void
    {
        $this->error = null;
        $this->flash = null;

        $candidateId = (int) $candidateId;

        if ($candidateId <= 0 || !in_array($newStage, $this->stageOrder, true)) {
            return;
        }

        $candidate = $this->findCandidate($candidateId);

        if (!$candidate) {
            return;
        }

        $current = $candidate['current_stage'] ?? 'applied';

        if ($current === $newStage) {
            return;
        }

        // rejections must go through the reject action so a reason is captured
        if ($newStage === 'rejected') {
            $this->openReject($candidateId);
            return;
        }

        // terminal stages cannot be moved out of
        if (in_array($current, ['hired', 'rejected', 'withdrawn'], true)) {
            $this->error = 'Cannot move a candidate that is already ' . ucfirst($current) . '.';
            return;
        }

        $response = Api::put('/recruitment/candidate/stage', [
            'candidate_id' => $candidateId,
            'stage' => $newStage,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to move candidate.');
            return;
        }

        $name = trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
        $this->flash = $name . ' → ' . ucfirst($newStage);
        $this->load();
    }

    // ---- arrow buttons ----

    public function advanceCard(int $candidateId): void
    {
        $candidate = $this->findCandidate($candidateId);

        if (!$candidate) {
            return;
        }

        $current = $candidate['current_stage'] ?? 'applied';
        $idx = array_search($current, $this->linearFlow, true);

        if ($idx === false || $idx >= count($this->linearFlow) - 1) {
            return;
        }

        $this->moveCard($candidateId, $this->linearFlow[$idx + 1]);
    }

    public function regressCard(int $candidateId): void
    {
        $candidate = $this->findCandidate($candidateId);

        if (!$candidate) {
            return;
        }

        $current = $candidate['current_stage'] ?? 'applied';
        $idx = array_search($current, $this->linearFlow, true);

        if ($idx === false || $idx === 0) {
            return;
        }

        $this->moveCard($candidateId, $this->linearFlow[$idx - 1]);
    }

    // ---- view ----

    public function openView(int $id): void
    {
        $this->error = null;

        $response = Api::get('/recruitment/candidate/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load candidate.';
            return;
        }

        $this->viewData = $response['data'] ?? null;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewData = null;
    }

    // ---- reject ----

    public function openReject(int $id): void
    {
        $candidate = $this->findCandidate($id);

        if (!$candidate) {
            return;
        }

        $this->rejectId = $id;
        $this->rejectName = trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
        $this->rejectReason = '';
        $this->error = null;
        $this->showReject = true;
        $this->closeView();
    }

    public function closeReject(): void
    {
        $this->showReject = false;
        $this->rejectId = null;
        $this->rejectReason = '';
    }

    public function submitReject(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->rejectReason)) {
            $this->error = 'Rejection reason is required.';
            return;
        }

        $response = Api::post('/recruitment/candidate/reject', [
            'candidate_id' => $this->rejectId,
            'reason' => $this->rejectReason,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to reject candidate.');
            return;
        }

        $this->flash = $this->rejectName . ' rejected.';
        $this->closeReject();
        $this->load();
    }

    // ---- helpers ----

    protected function findCandidate(int $id): ?array
    {
        foreach ($this->columns as $stage => $rows) {
            foreach ($rows as $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    return $row;
                }
            }
        }

        return null;
    }

    public function canMoveOut(array $candidate): bool
    {
        return !in_array($candidate['current_stage'] ?? '', ['hired', 'rejected', 'withdrawn'], true);
    }

    public function canAdvanceInLinear(array $candidate): bool
    {
        $stage = $candidate['current_stage'] ?? '';
        $idx = array_search($stage, $this->linearFlow, true);

        return $idx !== false && $idx < count($this->linearFlow) - 1;
    }

    public function canRegressInLinear(array $candidate): bool
    {
        $stage = $candidate['current_stage'] ?? '';
        $idx = array_search($stage, $this->linearFlow, true);

        return $idx !== false && $idx > 0;
    }

    public static function stageTone(string $stage): string
    {
        return match ($stage) {
            'applied'   => 'muted',
            'screening' => 'blue',
            'interview' => 'gold',
            'offer'     => 'purple',
            'hired'     => 'success',
            'rejected'  => 'red',
            default     => 'muted',
        };
    }

    public static function ageLabel(?string $appliedDate): string
    {
        if (!$appliedDate) {
            return '—';
        }

        $days = (int) \Carbon\Carbon::parse($appliedDate)->diffInDays(now());

        if ($days === 0) return 'today';
        if ($days === 1) return '1d ago';
        if ($days < 30) return $days . 'd ago';

        $months = (int) floor($days / 30);

        return $months . 'mo ago';
    }

    public function render()
    {
        return view('livewire.recruiter.pipeline')
            ->layout('layouts.portal', ['title' => 'Pipeline', 'breadcrumb' => 'Pipeline']);
    }
}