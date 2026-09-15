<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Timesheet Report - {{ $month }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #1a56db; padding-bottom: 10px; }
        .header h1 { color: #1a56db; margin: 0; font-size: 22px; }
        .header p { margin: 4px 0; color: #666; }
        .summary { background: #f3f4f6; padding: 12px; margin-bottom: 20px; border-radius: 4px; display: flex; justify-content: space-between; }
        .summary-item { text-align: center; flex: 1; }
        .summary-item .label { font-size: 9px; color: #666; text-transform: uppercase; }
        .summary-item .value { font-size: 16px; font-weight: bold; color: #1a56db; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #1a56db; color: white; padding: 8px 6px; text-align: left; font-size: 9px; }
        .data-table td { padding: 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        .data-table tr:nth-child(even) { background: #f9fafb; }
        .status-approved { color: #059669; font-weight: bold; }
        .status-pending { color: #d97706; font-weight: bold; }
        .status-rejected { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NKS Capital (Pty) Ltd</h1>
        <p>{{ ucfirst($report_type) }} Timesheet Report - {{ $month }}</p>
        <p>Generated: {{ $generated_at }} by {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <div class="summary-item">
            <div class="label">Total</div>
            <div class="value">{{ $summary['total'] }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Approved</div>
            <div class="value" style="color: #059669;">{{ $summary['approved'] }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Pending</div>
            <div class="value" style="color: #d97706;">{{ $summary['pending'] }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Rejected</div>
            <div class="value" style="color: #dc2626;">{{ $summary['rejected'] }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Total Hours</div>
            <div class="value">{{ number_format($summary['total_hours'], 2) }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Emp No</th>
                <th>Name</th>
                <th>Department</th>
                <th>Status</th>
                <th style="text-align: right;">Reg Hrs</th>
                <th style="text-align: right;">OT Hrs</th>
                <th>L1 Approver</th>
                <th>L2 Approver</th>
            </tr>
        </thead>
        <tbody>
            @foreach($timesheets as $ts)
                <tr>
                    <td>{{ $ts->user->employee_number }}</td>
                    <td>{{ $ts->user->full_name }}</td>
                    <td>{{ $ts->user->department ?? 'N/A' }}</td>
                    <td class="status-{{ $ts->status }}">{{ strtoupper($ts->status) }}</td>
                    <td style="text-align: right;">{{ number_format($ts->total_regular_hours, 2) }}</td>
                    <td style="text-align: right;">{{ number_format($ts->total_overtime_hours, 2) }}</td>
                    <td>{{ $ts->level1Approver?->full_name ?? 'N/A' }}</td>
                    <td>{{ $ts->level2Approver?->full_name ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This is a system-generated report from NKS Capital EMS</p>
    </div>
</body>
</html>