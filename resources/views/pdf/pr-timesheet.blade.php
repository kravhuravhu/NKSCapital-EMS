<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Timesheet - {{ $timesheet->month_year->format('F Y') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #1a56db; padding-bottom: 10px; }
        .header h1 { color: #1a56db; margin: 0; font-size: 20px; }
        .header p { margin: 4px 0; color: #666; }
        .info { margin-bottom: 20px; background: #f3f4f6; padding: 10px; border-radius: 4px; }
        .info table { width: 100%; }
        .info td { padding: 3px 8px; }
        .timesheet-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .timesheet-table th { background: #1a56db; color: white; padding: 8px; text-align: left; font-size: 10px; }
        .timesheet-table td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .summary { margin-top: 20px; background: #f3f4f6; padding: 12px; border-radius: 4px; }
        .approval-section { margin-top: 30px; }
        .approval-box { border: 1px solid #e5e7eb; padding: 15px; border-radius: 6px; margin-bottom: 15px; }
        .approval-box h3 { margin: 0 0 10px; font-size: 12px; color: #1a56db; }
        .approved { color: #059669; font-weight: bold; }
        .status-badge { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 12px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NKS Capital (Pty) Ltd</h1>
        <p>Internal Timesheet - {{ $timesheet->month_year->format('F Y') }}</p>
        <span class="status-badge">{{ strtoupper($timesheet->status) }}</span>
    </div>

    <div class="info">
        <table>
            <tr>
                <td><strong>Employee:</strong> {{ $user->full_name }}</td>
                <td><strong>Employee No:</strong> {{ $user->employee_number }}</td>
            </tr>
            <tr>
                <td><strong>Position:</strong> {{ $user->position ?? 'N/A' }}</td>
                <td><strong>Department:</strong> {{ $user->department ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Month:</strong> {{ $timesheet->month_year->format('F Y') }}</td>
                <td><strong>Generated:</strong> {{ $generated_at }}</td>
            </tr>
        </table>
    </div>

    <table class="timesheet-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Project</th>
                <th>Task Description</th>
                <th style="text-align: right;">Hours</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details->sortBy('work_date') as $detail)
                <tr>
                    <td>{{ $detail->work_date->format('d M Y') }}</td>
                    <td>{{ $detail->work_date->format('D') }}</td>
                    <td>{{ $detail->project->project_code ?? 'N/A' }}</td>
                    <td>{{ $detail->task_description ?? '-' }}</td>
                    <td style="text-align: right;">{{ number_format($detail->hours_worked, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr>
                <td><strong>Regular Hours:</strong></td>
                <td style="text-align: right;">{{ number_format($timesheet->total_regular_hours, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Overtime Hours:</strong></td>
                <td style="text-align: right;">{{ number_format($timesheet->total_overtime_hours, 2) }}</td>
            </tr>
            <tr style="border-top: 2px solid #1a56db;">
                <td><strong>Total Hours:</strong></td>
                <td style="text-align: right;"><strong>{{ number_format($details->sum('hours_worked'), 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="approval-section">
        <div class="approval-box">
            <h3>Level 1 Approval (Project Manager)</h3>
            <p><strong>Approver:</strong> {{ $timesheet->level1Approver->full_name ?? 'N/A' }}</p>
            <p><strong>Status:</strong> 
                @if($timesheet->level1_approved_at)
                    <span class="approved">Approved on {{ $timesheet->level1_approved_at->format('d M Y H:i') }}</span>
                @else
                    <span>Pending</span>
                @endif
            </p>
        </div>

        <div class="approval-box">
            <h3>Level 2 Approval (Director)</h3>
            <p><strong>Approver:</strong> {{ $timesheet->level2Approver->full_name ?? 'N/A' }}</p>
            <p><strong>Status:</strong> 
                @if($timesheet->level2_approved_at)
                    <span class="approved">Approved on {{ $timesheet->level2_approved_at->format('d M Y H:i') }}</span>
                @else
                    <span>Pending</span>
                @endif
            </p>
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px; font-size: 9px; color: #999;">
        This timesheet was digitally approved via NKS Capital EMS on {{ $generated_at }}<br>
        PDF Hash: {{ $timesheet->pdf_hash ?? 'N/A' }}
    </div>
</body>
</html>