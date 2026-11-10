<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Livewire\Component;
use Livewire\WithFileUploads;

class Candidates extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $stageFilter = '';
    public string $sourceFilter = '';
    public string $requisitionFilter = '';
    public int $page = 1;

    public array $candidates = [];
    public array $pagination = [];
    public array $requisitions = [];

    // view
    public bool $showView = false;
    public ?array $viewData = null;

    // create
    public bool $showCreate = false;
    public array $createForm = [];
    public $resumeFile = null;
    public bool $parsing = false;
    public bool $parsed = false;
    public ?string $parseWarning = null;

    // advance stage
    public bool $showAdvance = false;
    public ?int $advanceId = null;
    public string $advanceName = '';
    public string $advanceCurrentStage = '';
    public string $advanceStage = '';
    public string $advanceNotes = '';

    // reject
    public bool $showReject = false;
    public ?int $rejectId = null;
    public string $rejectName = '';
    public string $rejectReason = '';

    public function mount(): void
    {
        $this->resetCreateForm();
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

        $params = ['per_page' => 20];

        if ($this->search !== '')            $params['search'] = $this->search;
        if ($this->stageFilter !== '')       $params['stage'] = $this->stageFilter;
        if ($this->sourceFilter !== '')      $params['source'] = $this->sourceFilter;
        if ($this->requisitionFilter !== '') $params['requisition_id'] = $this->requisitionFilter;
        if ($this->page > 1)                 $params['page'] = $this->page;

        $response = Api::get('/recruitment/candidate/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load candidates.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->candidates = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page' => $paginator['last_page'] ?? 1,
            'total' => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->stageFilter = '';
        $this->sourceFilter = '';
        $this->requisitionFilter = '';
        $this->page = 1;
        $this->load();
    }

    public function selectStage(string $stage): void
    {
        $this->stageFilter = $stage;
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    // ============ view ============

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

    // ============ create ============

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'requisition_id' => '',
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'years_experience' => '',
            'expected_salary' => '',
            'skills' => '',
            'source' => 'manual',
            'linkedin_url' => '',
            'notes' => '',
        ];
        $this->resumeFile = null;
        $this->parsed = false;
        $this->parseWarning = null;
    }

    public function openCreate(?int $requisitionId = null): void
    {
        $this->resetCreateForm();
        if ($requisitionId) {
            $this->createForm['requisition_id'] = (string) $requisitionId;
        }
        $this->error = null;
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
        $this->resetCreateForm();
    }

    public function parseResume(): void
    {
        $this->error = null;
        $this->parseWarning = null;
        $this->parsed = false;

        if (!$this->resumeFile) {
            $this->error = 'Select a resume file first.';
            return;
        }

        $this->parsing = true;

        $response = Api::upload('/recruitment/candidate/parse-resume', [], [
            'resume' => $this->resumeFile,
        ]);

        if (!$response['ok']) {
            $this->parseWarning = $response['message'] ?? 'Could not parse resume. Enter details manually.';
            $this->parsing = false;
            return;
        }

        $data = $response['data'] ?? [];

        // split full_name into first/last if present
        if (!empty($data['full_name'])) {
            $parts = preg_split('/\s+/', trim($data['full_name']), 2);
            if (empty($this->createForm['first_name']) && !empty($parts[0])) {
                $this->createForm['first_name'] = $parts[0];
            }
            if (empty($this->createForm['last_name']) && !empty($parts[1])) {
                $this->createForm['last_name'] = $parts[1];
            }
        }

        // only fill fields that are currently empty — never overwrite what the user typed
        if (!empty($data['email']) && empty($this->createForm['email'])) {
            $this->createForm['email'] = $data['email'];
        }
        if (!empty($data['phone']) && empty($this->createForm['phone'])) {
            $this->createForm['phone'] = $data['phone'];
        }
        if (!empty($data['linkedin_url']) && empty($this->createForm['linkedin_url'])) {
            $this->createForm['linkedin_url'] = $data['linkedin_url'];
        }
        if (isset($data['years_experience']) && $data['years_experience'] !== null && empty($this->createForm['years_experience'])) {
            $this->createForm['years_experience'] = (string) $data['years_experience'];
        }
        if (!empty($data['skills']) && is_array($data['skills']) && empty($this->createForm['skills'])) {
            $this->createForm['skills'] = implode(', ', $data['skills']);
        }

        $this->parsed = true;
        $this->parsing = false;
    }

    public function submitCreate(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->createForm['requisition_id'])) {
            $this->error = 'Select a requisition.';
            return;
        }
        if (empty($this->createForm['first_name']) || empty($this->createForm['last_name'])) {
            $this->error = 'First and last name are required.';
            return;
        }
        if (empty($this->createForm['email'])) {
            $this->error = 'Email is required.';
            return;
        }

        $payload = $this->buildCreatePayload();

        $files = $this->resumeFile ? ['resume' => $this->resumeFile] : [];

        $response = $files
            ? Api::upload('/recruitment/candidate/add', $payload, $files)
            : Api::post('/recruitment/candidate/add', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to add candidate.');
            return;
        }

        $this->flash = 'Candidate added.';
        $this->closeCreate();
        $this->load();
    }

    protected function buildCreatePayload(): array
    {
        $skills = [];
        if (!empty($this->createForm['skills'])) {
            $skills = array_values(array_filter(array_map('trim', explode(',', $this->createForm['skills']))));
        }

        $payload = [
            'requisition_id' => $this->createForm['requisition_id'],
            'first_name' => $this->createForm['first_name'],
            'last_name' => $this->createForm['last_name'],
            'email' => $this->createForm['email'],
            'source' => $this->createForm['source'] ?: 'manual',
            'skills' => $skills,
        ];

        $optional = ['phone', 'years_experience', 'expected_salary', 'linkedin_url', 'notes'];
        foreach ($optional as $field) {
            if (!empty($this->createForm[$field])) {
                $payload[$field] = $this->createForm[$field];
            }
        }

        return $payload;
    }

    // ============ advance stage ============

    public function openAdvance(int $id): void
    {
        $row = collect($this->candidates)->firstWhere('id', $id);
        if (!$row) {
            $this->error = 'Candidate not found.';
            return;
        }

        $current = $row['current_stage'] ?? 'applied';

        if (in_array($current, ['hired', 'rejected', 'withdrawn'], true)) {
            $this->error = 'Cannot advance a candidate in stage ' . ucfirst($current) . '.';
            return;
        }

        $this->advanceId = $id;
        $this->advanceName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $this->advanceCurrentStage = $current;
        $this->advanceStage = '';
        $this->advanceNotes = '';
        $this->error = null;
        $this->showAdvance = true;
        $this->closeView();
    }

    public function closeAdvance(): void
    {
        $this->showAdvance = false;
        $this->advanceId = null;
        $this->advanceStage = '';
        $this->advanceNotes = '';
    }

    public function submitAdvance(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->advanceStage)) {
            $this->error = 'Select a new stage.';
            return;
        }

        $response = Api::put('/recruitment/candidate/stage', [
            'candidate_id' => $this->advanceId,
            'stage' => $this->advanceStage,
            'notes' => $this->advanceNotes ?: null,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to advance candidate.');
            return;
        }

        $this->flash = $this->advanceName . ' moved to ' . ucfirst(str_replace('_', ' ', $this->advanceStage)) . '.';
        $this->closeAdvance();
        $this->load();
    }

    // ============ reject ============

    public function openReject(int $id): void
    {
        $row = collect($this->candidates)->firstWhere('id', $id);
        if (!$row) {
            $this->error = 'Candidate not found.';
            return;
        }

        if (in_array($row['current_stage'] ?? '', ['hired', 'rejected', 'withdrawn'], true)) {
            $this->error = 'Cannot reject a candidate in this stage.';
            return;
        }

        $this->rejectId = $id;
        $this->rejectName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
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

    // ============ helpers ============

    public static function stageTone(string $stage): string
    {
        return match ($stage) {
            'applied'        => 'muted',
            'screening'      => 'blue',
            'interview'      => 'gold',
            'offer'          => 'purple',
            'hired'          => 'success',
            'rejected'       => 'red',
            'offer_declined' => 'red',
            'withdrawn'      => 'muted',
            'on_hold'        => 'gold',
            default          => 'muted',
        };
    }

    public function stageOptions(): array
    {
        // exclude the candidate's current stage and the terminal "rejected" stage
        $all = ['applied', 'screening', 'interview', 'offer', 'hired', 'offer_declined', 'withdrawn', 'on_hold'];
        return array_values(array_filter(
            $all,
            fn ($s) => $s !== $this->advanceCurrentStage && $s !== 'rejected'
        ));
    }

    public function render()
    {
        return view('livewire.recruiter.candidates')
            ->layout('layouts.portal', ['title' => 'Candidates', 'breadcrumb' => 'Candidates']);
    }
}