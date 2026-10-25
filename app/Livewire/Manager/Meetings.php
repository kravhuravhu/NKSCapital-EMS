<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class Meetings extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $scope = 'upcoming';
    public string $statusFilter = '';
    public string $typeFilter = '';

    public array $meetings = [];
    public array $pagination = [];

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    public bool $showView = false;
    public ?array $viewData = null;
    public ?array $qrData = null;

    public bool $showCancel = false;
    public ?int $cancelId = null;
    public string $cancelReason = '';

    public bool $showAttendance = false;
    public ?array $attendanceData = null;

    public function mount(): void
    {
        $this->resetForm();
        $this->load();
    }

    public function resetForm(): void
    {
        $start = now()->addDay()->setTime(9, 0);
        $end = $start->copy()->addHour();

        $this->form = [
            'title' => '',
            'type' => 'general',
            'is_mandatory' => false,
            'late_threshold_minutes' => 5,
            'excessive_late_threshold_minutes' => 20,
            'department' => auth()->user()->department ?? '',
            'description' => '',
            'start_time' => $start->format('Y-m-d\TH:i'),
            'end_time' => $end->format('Y-m-d\TH:i'),
            'location' => '',
            'meeting_link' => '',
        ];
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['scope' => $this->scope, 'per_page' => 15];

        if ($this->statusFilter) $params['status'] = $this->statusFilter;
        if ($this->typeFilter) $params['type'] = $this->typeFilter;

        $response = Api::get('/meeting/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load meetings.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];

        $this->meetings = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page' => $paginator['last_page'] ?? 1,
            'total' => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function selectScope(string $scope): void
    {
        if (!in_array($scope, ['upcoming', 'today', 'past', 'all'], true)) {
            return;
        }

        $this->scope = $scope;
        $this->load();
    }

    public function updatedStatusFilter(): void
    {
        $this->load();
    }

    public function updatedTypeFilter(): void
    {
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        // paginator reads page from the query string, so passing per_page keeps the offset
        $this->load();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->error = null;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $meeting = collect($this->meetings)->firstWhere('id', $id);

        if (!$meeting) {
            $this->error = 'Meeting not found.';
            return;
        }

        $this->editingId = $id;
        $this->form = [
            'title' => $meeting['title'] ?? '',
            'type' => $meeting['type'] ?? 'general',
            'is_mandatory' => (bool) ($meeting['is_mandatory'] ?? false),
            'late_threshold_minutes' => (int) ($meeting['late_threshold_minutes'] ?? 5),
            'excessive_late_threshold_minutes' => (int) ($meeting['excessive_late_threshold_minutes'] ?? 20),
            'department' => $meeting['department'] ?? '',
            'description' => $meeting['description'] ?? '',
            'start_time' => Carbon::parse($meeting['start_time'])->format('Y-m-d\TH:i'),
            'end_time' => Carbon::parse($meeting['end_time'])->format('Y-m-d\TH:i'),
            'location' => $meeting['location'] ?? '',
            'meeting_link' => $meeting['meeting_link'] ?? '',
        ];

        $this->error = null;
        $this->showForm = true;
        $this->closeView();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->error = null;
        $this->flash = null;

        $payload = $this->form;

        // datetime-local to API date format
        $payload['start_time'] = Carbon::parse($payload['start_time'])->format('Y-m-d H:i:s');
        $payload['end_time'] = Carbon::parse($payload['end_time'])->format('Y-m-d H:i:s');

        // drop empty optional fields so validation doesn't trip on blank strings
        $payload = array_filter($payload, fn ($v) => $v !== '' && $v !== null);

        $response = $this->editingId
            ? Api::put('/meeting/update/' . $this->editingId, $payload)
            : Api::post('/meeting/schedule', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to save meeting.');
            return;
        }

        $this->flash = $this->editingId ? 'Meeting updated.' : 'Meeting scheduled.';
        $this->closeForm();
        $this->load();
    }

    public function view(int $id): void
    {
        $meeting = collect($this->meetings)->firstWhere('id', $id);

        if (!$meeting) {
            $this->error = 'Meeting not found.';
            return;
        }

        $this->viewData = $meeting;
        $this->qrData = null;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewData = null;
        $this->qrData = null;
    }

    public function loadQr(int $id): void
    {
        $this->qrData = null;

        $response = Api::get('/meeting/qr/' . $id);

        if (!$response['ok']) {
            $this->qrData = ['error' => $response['message'] ?? 'QR code not available right now.'];
            return;
        }

        $this->qrData = $response['data'] ?? null;
    }

    public function downloadIcal(int $id)
    {
        $meeting = collect($this->meetings)->firstWhere('id', $id);

        if (!$meeting) {
            $this->error = 'Meeting not found.';
            return;
        }

        $ics = $this->buildIcal($meeting);

        return response()->streamDownload(function () use ($ics) {
            echo $ics;
        }, 'meeting-' . $id . '.ics', ['Content-Type' => 'text/calendar; charset=utf-8']);
    }

    protected function buildIcal(array $meeting): string
    {
        $format = fn ($dt) => Carbon::parse($dt)->utc()->format('Ymd\THis\Z');
        $uid = 'meeting-' . $meeting['id'] . '@nkscapital.co.za';

        $summary = addslashes($meeting['title'] ?? '');
        $description = addslashes($meeting['description'] ?? '');
        $location = addslashes($meeting['location'] ?? $meeting['meeting_link'] ?? '');
        $created = $format($meeting['created_at'] ?? now());
        $dtstart = $format($meeting['start_time']);
        $dtend = $format($meeting['end_time']);

        return "BEGIN:VCALENDAR\r\n" .
            "VERSION:2.0\r\n" .
            "PRODID:-//NKS Capital//EMS//EN\r\n" .
            "CALSCALE:GREGORIAN\r\n" .
            "METHOD:PUBLISH\r\n" .
            "BEGIN:VEVENT\r\n" .
            "UID:{$uid}\r\n" .
            "DTSTAMP:{$created}\r\n" .
            "DTSTART:{$dtstart}\r\n" .
            "DTEND:{$dtend}\r\n" .
            "SUMMARY:{$summary}\r\n" .
            "DESCRIPTION:{$description}\r\n" .
            "LOCATION:{$location}\r\n" .
            "STATUS:CONFIRMED\r\n" .
            "END:VEVENT\r\n" .
            "END:VCALENDAR\r\n";
    }

    public function openCancel(int $id): void
    {
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->showCancel = true;
    }

    public function closeCancel(): void
    {
        $this->showCancel = false;
        $this->cancelId = null;
        $this->cancelReason = '';
    }

    public function submitCancel(): void
    {
        $this->error = null;

        if (!$this->cancelReason) {
            $this->error = 'Please provide a cancellation reason.';
            return;
        }

        $response = Api::post('/meeting/cancel/' . $this->cancelId, [
            'reason' => $this->cancelReason,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to cancel meeting.';
            return;
        }

        $this->flash = 'Meeting cancelled.';
        $this->closeCancel();
        $this->closeView();
        $this->load();
    }

    public function openAttendance(int $id): void
    {
        $this->attendanceData = null;
        $this->showAttendance = true;

        $response = Api::get('/meeting/attendance/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load attendance.';
            $this->showAttendance = false;
            return;
        }

        $this->attendanceData = $response['data'] ?? null;
    }

    public function closeAttendance(): void
    {
        $this->showAttendance = false;
        $this->attendanceData = null;
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'scheduled'  => 'gold',
            'ongoing'    => 'success',
            'completed'  => 'muted',
            'cancelled'  => 'red',
            'postponed'  => 'blue',
            default      => 'muted',
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'general'  => 'fa-solid fa-chalkboard-user',
            'team'     => 'fa-solid fa-users',
            'client'   => 'fa-solid fa-handshake',
            'training' => 'fa-solid fa-graduation-cap',
            'review'   => 'fa-solid fa-magnifying-glass-chart',
            default    => 'fa-solid fa-calendar',
        };
    }

    public static function lateTone(string $lateStatus): string
    {
        return match ($lateStatus) {
            'on_time' => 'success',
            'late'    => 'gold',
            'excessive_late' => 'red',
            default => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.manager.meetings')
            ->layout('layouts.portal', ['title' => 'Meetings', 'breadcrumb' => 'Meetings']);
    }
}