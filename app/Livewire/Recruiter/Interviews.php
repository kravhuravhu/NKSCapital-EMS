<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class Interviews extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $view = 'list';
    public string $statusFilter = '';
    public string $interviewerFilter = '';
    public string $fromFilter = '';
    public string $toFilter = '';
    public int $page = 1;

    public array $interviews = [];
    public array $pagination = [];

    public string $calendarMonth = '';
    public array $calendarDays = [];
    public int $calendarTotal = 0;

    public array $candidates = [];
    public array $interviewers = [];

    public bool $showView = false;
    public ?array $viewData = null;

    public bool $showSchedule = false;
    public array $scheduleForm = [];

    public bool $showReschedule = false;
    public ?int $rescheduleId = null;
    public string $rescheduleName = '';
    public array $rescheduleForm = [];

    public bool $showCancel = false;
    public ?int $cancelId = null;
    public string $cancelName = '';
    public string $cancelReason = '';

    public bool $showFeedback = false;
    public ?int $feedbackId = null;
    public string $feedbackName = '';
    public array $feedbackForm = [];

    public function mount(): void
    {
        $this->calendarMonth = now()->format('Y-m');
        $this->resetScheduleForm();
        $this->loadReferenceData();
        $this->loadList();
    }

    protected function loadReferenceData(): void
    {
        // interviewers — everyone active, filtered by role below
        $usr = Api::get('/employee/list', ['per_page' => 300, 'is_active' => 1]);
        if ($usr['ok']) {
            $all = $usr['data']['data'] ?? [];
            $this->interviewers = array_values(array_filter(
                $all,
                fn ($u) => in_array($u['role'] ?? '', ['manager', 'director', 'admin', 'recruiter'], true)
            ));
        }

        // candidates for the schedule picker — only those still in the pipeline
        $cd = Api::get('/recruitment/candidate/list', ['per_page' => 300]);
        if ($cd['ok']) {
            $all = $cd['data']['data'] ?? [];
            $this->candidates = array_values(array_filter(
                $all,
                fn ($c) => !in_array($c['current_stage'] ?? '', ['hired', 'rejected', 'withdrawn'], true)
            ));
        }
    }

    // ============ list ============

    public function loadList(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->statusFilter !== '')      $params['status'] = $this->statusFilter;
        if ($this->interviewerFilter !== '') $params['interviewer_id'] = $this->interviewerFilter;
        if ($this->fromFilter !== '')        $params['from'] = $this->fromFilter;
        if ($this->toFilter !== '')          $params['to'] = $this->toFilter;
        if ($this->page > 1)                 $params['page'] = $this->page;

        $response = Api::get('/recruitment/interview/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load interviews.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->interviews = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page' => $paginator['last_page'] ?? 1,
            'total' => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    // ============ calendar ============

    public function loadCalendar(): void
    {
        $params = ['month' => $this->calendarMonth];

        if ($this->interviewerFilter !== '') {
            $params['interviewer_id'] = $this->interviewerFilter;
        }

        $response = Api::get('/recruitment/interview/calendar', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load calendar.';
            return;
        }

        $data = $response['data'] ?? [];
        $this->calendarTotal = (int) ($data['total'] ?? 0);
        $this->calendarDays = $this->buildCalendarGrid($data['by_date'] ?? []);
    }

    protected function buildCalendarGrid(array $byDate): array
    {
        $monthStart = Carbon::parse($this->calendarMonth . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $keyed = collect($byDate)->keyBy('date');

        $startPadding = $monthStart->dayOfWeekIso - 1;
        $days = [];

        for ($i = 0; $i < $startPadding; $i++) {
            $days[] = $this->emptyCell();
        }

        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $key = $cursor->format('Y-m-d');
            $entry = $keyed->get($key);

            $days[] = [
                'day' => $cursor->day,
                'date' => $key,
                'is_weekend' => $cursor->isWeekend(),
                'is_today' => $cursor->isToday(),
                'count' => $entry['count'] ?? 0,
                'interviews' => $entry['interviews'] ?? [],
            ];

            $cursor->addDay();
        }

        $remainder = 7 - (count($days) % 7);
        if ($remainder < 7) {
            for ($i = 0; $i < $remainder; $i++) {
                $days[] = $this->emptyCell();
            }
        }

        return $days;
    }

    protected function emptyCell(): array
    {
        return [
            'day' => null,
            'date' => null,
            'is_weekend' => false,
            'is_today' => false,
            'count' => 0,
            'interviews' => [],
        ];
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->subMonth()->format('Y-m');
        $this->loadCalendar();
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->addMonth()->format('Y-m');
        $this->loadCalendar();
    }

    public function goToday(): void
    {
        $this->calendarMonth = now()->format('Y-m');
        $this->loadCalendar();
    }

    // ============ switching views ============

    public function selectView(string $view): void
    {
        if (!in_array($view, ['list', 'calendar'], true)) {
            return;
        }

        $this->view = $view;

        if ($view === 'calendar') {
            $this->loadCalendar();
        } else {
            $this->loadList();
        }
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->loadList();
        $this->loadCalendar();
    }

    public function resetFilters(): void
    {
        $this->statusFilter = '';
        $this->interviewerFilter = '';
        $this->fromFilter = '';
        $this->toFilter = '';
        $this->page = 1;
        $this->loadList();
        $this->loadCalendar();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->loadList();
    }

    // ============ schedule ============

    public function resetScheduleForm(): void
    {
        $start = now()->addDay()->setTime(10, 0);

        $this->scheduleForm = [
            'candidate_id' => '',
            'interviewer_id' => '',
            'scheduled_at' => $start->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'interview_type' => 'video',
            'round' => 1,
            'location_or_link' => '',
            'meeting_link' => '',
        ];
    }

    public function openSchedule(?int $candidateId = null): void
    {
        $this->resetScheduleForm();
        if ($candidateId) {
            $this->scheduleForm['candidate_id'] = (string) $candidateId;
        }
        $this->error = null;
        $this->showSchedule = true;
    }

    public function closeSchedule(): void
    {
        $this->showSchedule = false;
        $this->resetScheduleForm();
    }

    public function submitSchedule(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->scheduleForm['candidate_id'])) {
            $this->error = 'Select a candidate.';
            return;
        }
        if (empty($this->scheduleForm['interviewer_id'])) {
            $this->error = 'Select an interviewer.';
            return;
        }
        if (empty($this->scheduleForm['scheduled_at'])) {
            $this->error = 'Pick a date and time.';
            return;
        }

        $payload = [
            'candidate_id' => $this->scheduleForm['candidate_id'],
            'interviewer_id' => $this->scheduleForm['interviewer_id'],
            'scheduled_at' => Carbon::parse($this->scheduleForm['scheduled_at'])->format('Y-m-d H:i:s'),
            'duration_minutes' => (int) $this->scheduleForm['duration_minutes'],
            'interview_type' => $this->scheduleForm['interview_type'],
            'round' => (int) $this->scheduleForm['round'],
        ];

        if (!empty($this->scheduleForm['location_or_link'])) {
            $payload['location_or_link'] = $this->scheduleForm['location_or_link'];
        }
        if (!empty($this->scheduleForm['meeting_link'])) {
            $payload['meeting_link'] = $this->scheduleForm['meeting_link'];
        }

        $response = Api::post('/recruitment/interview/schedule', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to schedule interview.');
            return;
        }

        $this->flash = 'Interview scheduled.';
        $this->closeSchedule();
        $this->loadList();
        if ($this->view === 'calendar') {
            $this->loadCalendar();
        }
    }

    // ============ reschedule ============

    public function openReschedule(int $id): void
    {
        $row = collect($this->interviews)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Interview not found.';
            return;
        }

        $status = $row['status'] ?? '';
        if (!in_array($status, ['scheduled', 'rescheduled'], true)) {
            $this->error = 'Only scheduled or rescheduled interviews can be moved.';
            return;
        }

        $this->rescheduleId = $id;
        $this->rescheduleName = trim(
            ($row['candidate']['first_name'] ?? '') . ' ' . ($row['candidate']['last_name'] ?? '')
        );

        $this->rescheduleForm = [
            'scheduled_at' => !empty($row['scheduled_at'])
                ? Carbon::parse($row['scheduled_at'])->format('Y-m-d\TH:i')
                : now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
            'duration_minutes' => $row['duration_minutes'] ?? 60,
            'location_or_link' => $row['location_or_link'] ?? '',
            'meeting_link' => $row['meeting_link'] ?? '',
        ];

        $this->error = null;
        $this->showReschedule = true;
        $this->closeView();
    }

    public function closeReschedule(): void
    {
        $this->showReschedule = false;
        $this->rescheduleId = null;
        $this->rescheduleName = '';
        $this->rescheduleForm = [];
    }

    public function submitReschedule(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->rescheduleForm['scheduled_at'])) {
            $this->error = 'Pick a new date and time.';
            return;
        }

        $payload = [
            'scheduled_at' => Carbon::parse($this->rescheduleForm['scheduled_at'])->format('Y-m-d H:i:s'),
            'duration_minutes' => (int) $this->rescheduleForm['duration_minutes'],
        ];

        if (!empty($this->rescheduleForm['location_or_link'])) {
            $payload['location_or_link'] = $this->rescheduleForm['location_or_link'];
        }
        if (!empty($this->rescheduleForm['meeting_link'])) {
            $payload['meeting_link'] = $this->rescheduleForm['meeting_link'];
        }

        $response = Api::put('/recruitment/interview/reschedule/' . $this->rescheduleId, $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to reschedule.');
            return;
        }

        $this->flash = 'Interview rescheduled.';
        $this->closeReschedule();
        $this->loadList();
        if ($this->view === 'calendar') {
            $this->loadCalendar();
        }
    }

    // ============ cancel ============

    public function openCancel(int $id): void
    {
        $row = collect($this->interviews)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Interview not found.';
            return;
        }

        $status = $row['status'] ?? '';
        if (!in_array($status, ['scheduled', 'rescheduled'], true)) {
            $this->error = 'Only scheduled or rescheduled interviews can be cancelled.';
            return;
        }

        $this->cancelId = $id;
        $this->cancelName = trim(
            ($row['candidate']['first_name'] ?? '') . ' ' . ($row['candidate']['last_name'] ?? '')
        );
        $this->cancelReason = '';
        $this->error = null;
        $this->showCancel = true;
        $this->closeView();
    }

    public function closeCancel(): void
    {
        $this->showCancel = false;
        $this->cancelId = null;
        $this->cancelName = '';
        $this->cancelReason = '';
    }

    public function submitCancel(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->cancelReason)) {
            $this->error = 'Cancellation reason is required.';
            return;
        }

        $response = Api::post('/recruitment/interview/cancel/' . $this->cancelId, [
            'reason' => $this->cancelReason,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to cancel interview.');
            return;
        }

        $this->flash = 'Interview cancelled.';
        $this->closeCancel();
        $this->loadList();
        if ($this->view === 'calendar') {
            $this->loadCalendar();
        }
    }

    // ============ feedback ============

    public function openFeedback(int $id): void
    {
        $row = collect($this->interviews)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Interview not found.';
            return;
        }

        if (!$this->isInterviewer($row)) {
            $this->error = 'Only the assigned interviewer can submit feedback.';
            return;
        }

        if (!$this->canSubmitFeedbackNow($row)) {
            $this->error = 'Feedback cannot be submitted for this interview.';
            return;
        }

        $this->feedbackId = $id;
        $this->feedbackName = trim(
            ($row['candidate']['first_name'] ?? '') . ' ' . ($row['candidate']['last_name'] ?? '')
        );

        $this->feedbackForm = [
            'rating' => 3,
            'recommendation' => 'undecided',
            'notes' => '',
        ];

        $this->error = null;
        $this->showFeedback = true;
        $this->closeView();
    }

    public function closeFeedback(): void
    {
        $this->showFeedback = false;
        $this->feedbackId = null;
        $this->feedbackName = '';
        $this->feedbackForm = [];
    }

    public function submitFeedback(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->feedbackForm['recommendation'])) {
            $this->error = 'Select a recommendation.';
            return;
        }

        $payload = [
            'interview_id' => $this->feedbackId,
            'rating' => (int) $this->feedbackForm['rating'],
            'recommendation' => $this->feedbackForm['recommendation'],
        ];

        if (!empty($this->feedbackForm['notes'])) {
            $payload['notes'] = $this->feedbackForm['notes'];
        }

        $response = Api::post('/recruitment/interview/feedback', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to submit feedback.');
            return;
        }

        $this->flash = 'Feedback submitted.';
        $this->closeFeedback();
        $this->loadList();
    }

    // ============ view ============

    public function openView(int $id): void
    {
        $this->error = null;

        $response = Api::get('/recruitment/interview/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load interview.';
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

    // ============ helpers ============

    protected function isInterviewer(array $row): bool
    {
        return (int) ($row['interviewer_id'] ?? 0) === (int) auth()->id();
    }

    protected function canSubmitFeedbackNow(array $row): bool
    {
        if (!empty($row['feedback_rating'])) return false;
        if (in_array($row['status'] ?? '', ['cancelled', 'no_show'], true)) return false;
        if (empty($row['scheduled_at'])) return false;

        return Carbon::parse($row['scheduled_at'])->isPast();
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'scheduled'         => 'blue',
            'rescheduled'       => 'gold',
            'completed'         => 'success',
            'cancelled'         => 'red',
            'no_show'           => 'red',
            'feedback_pending'  => 'gold',
            default             => 'muted',
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'phone'     => 'fa-solid fa-phone',
            'video'     => 'fa-solid fa-video',
            'in_person' => 'fa-solid fa-building-user',
            default     => 'fa-solid fa-comments',
        };
    }

    public static function recommendationTone(string $rec): string
    {
        return match ($rec) {
            'strong_yes' => 'success',
            'yes'        => 'success',
            'no'         => 'red',
            'strong_no'  => 'red',
            'undecided'  => 'muted',
            default      => 'muted',
        };
    }

    public function canRescheduleRow(array $row): bool
    {
        return in_array($row['status'] ?? '', ['scheduled', 'rescheduled'], true);
    }

    public function canCancelRow(array $row): bool
    {
        return in_array($row['status'] ?? '', ['scheduled', 'rescheduled'], true);
    }

    public function canFeedbackRow(array $row): bool
    {
        return $this->isInterviewer($row) && $this->canSubmitFeedbackNow($row);
    }

    public function render()
    {
        return view('livewire.recruiter.interviews')
            ->layout('layouts.portal', ['title' => 'Interviews', 'breadcrumb' => 'Interviews']);
    }
}