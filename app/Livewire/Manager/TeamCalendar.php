<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class TeamCalendar extends Component
{
    public bool $loading = true;
    public ?string $error = null;

    public string $month = '';
    public string $monthLabel = '';

    public array $days = [];
    public array $byType = [];
    public int $totalLeaves = 0;

    public bool $showDay = false;
    public ?string $dayDate = null;
    public array $dayEmployees = [];

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $response = Api::get('/leave/calendar', [
            'month' => $this->month,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load team calendar.';
            $this->loading = false;
            return;
        }

        $data = $response['data'] ?? [];

        $this->monthLabel = $data['month'] ?? Carbon::parse($this->month . '-01')->format('F Y');
        $this->totalLeaves = (int) ($data['total_leaves'] ?? 0);
        $this->byType = $data['by_type'] ?? [];

        // build the 7-column grid, padding empty cells at start and end
        $this->days = $this->buildCalendar($data['by_date'] ?? []);

        $this->loading = false;
    }

    protected function buildCalendar(array $byDate): array
    {
        $monthStart = Carbon::parse($this->month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $byDateKeyed = collect($byDate)->keyBy('date');

        // pad start so the first week begins on Monday
        $startPadding = $monthStart->dayOfWeekIso - 1;

        $days = [];

        for ($i = 0; $i < $startPadding; $i++) {
            $days[] = $this->emptyCell();
        }

        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $dateStr = $cursor->format('Y-m-d');
            $entry = $byDateKeyed->get($dateStr);

            $days[] = [
                'day' => $cursor->day,
                'date' => $dateStr,
                'is_weekend' => $cursor->isWeekend(),
                'is_today' => $cursor->isToday(),
                'count' => $entry['count'] ?? 0,
                'employees' => $entry['employees'] ?? [],
            ];

            $cursor->addDay();
        }

        // pad end so the final week is complete
        $remaining = 7 - (count($days) % 7);

        if ($remaining < 7) {
            for ($i = 0; $i < $remaining; $i++) {
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
            'employees' => [],
        ];
    }

    public function updatedMonth(): void
    {
        $this->load();
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::parse($this->month . '-01')->subMonth()->format('Y-m');
        $this->load();
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::parse($this->month . '-01')->addMonth()->format('Y-m');
        $this->load();
    }

    public function goToday(): void
    {
        $this->month = now()->format('Y-m');
        $this->load();
    }

    public function selectDay(string $date): void
    {
        $entry = collect($this->days)->firstWhere('date', $date);

        if (!$entry || empty($entry['employees'])) {
            return;
        }

        $this->dayDate = $date;
        $this->dayEmployees = $entry['employees'];
        $this->showDay = true;
    }

    public function closeDay(): void
    {
        $this->showDay = false;
        $this->dayDate = null;
        $this->dayEmployees = [];
    }

    public static function typeTone(string $type): string
    {
        return match ($type) {
            'annual'    => 'success',
            'sick'      => 'red',
            'family'    => 'blue',
            'unpaid'    => 'gold',
            'study'     => 'purple',
            'maternity' => 'purple',
            'paternity' => 'purple',
            default     => 'muted',
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'annual'    => 'fa-solid fa-umbrella-beach',
            'sick'      => 'fa-solid fa-thermometer-half',
            'family'    => 'fa-solid fa-users',
            'unpaid'    => 'fa-solid fa-coins',
            'study'     => 'fa-solid fa-graduation-cap',
            'maternity' => 'fa-solid fa-baby',
            'paternity' => 'fa-solid fa-person-breastfeeding',
            default     => 'fa-solid fa-calendar',
        };
    }

    public function render()
    {
        return view('livewire.manager.team-calendar')
            ->layout('layouts.portal', ['title' => 'Team Calendar', 'breadcrumb' => 'Team Calendar']);
    }
}