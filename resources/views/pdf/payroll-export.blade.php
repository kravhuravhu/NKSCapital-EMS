<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Export - {{ $payroll['summary']['generated_at'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #1a56db; padding-bottom: 10px; }
        .header h1 { color: #1a56db; margin: 0; font-size: 22px; }
        .header p { margin: 4px 0; color: #666; }
        .summary { background: #f3f4f6; padding: 12px; margin-bottom: 20px; border-radius: 4px; }
        .summary table { width: 100%; }
        .summary td { padding: 4px 8px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #1a56db; color: white; padding: 8px 6px; text-align: left; font-size: 9px; }
        .data-table td { padding: 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        .data-table tr:nth-child(even) { background: #f9fafb; }
        .total-row { background: #dbeafe !important; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NKS Capital (Pty) Ltd</h1>
        <p>Payroll Export Report</p>
        <p>Generated: {{ $generated_at }} by {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td><strong>Total Employees:</strong> {{ $payroll['summary']['total_employees'] }}</td>
                <td><strong>Total Regular Hours:</strong> {{ number_format($payroll['summary']['total_regular_hours'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Total Overtime Hours:</strong> {{ number_format($payroll['summary']['total_overtime_hours'], 2) }}</td>
                <td><strong>Total Amount:</strong> R {{ number_format($payroll['summary']['total_amount'], 2) }}</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Emp No</th>
                <th>Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Month</th>
                <th style="text-align: right;">Reg Hrs</th>
                <th style="text-align: right;">OT Hrs</th>
                <th style="text-align: right;">Rate</th>
                <th style="text-align: right;">Reg Amt</th>
                <th style="text-align: right;">OT Amt</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payroll['timesheets'] as $ts)
                <tr>
                    <td>{{ $ts['employee_number'] }}</td>
                    <td>{{ $ts['employee_name'] }}</td>
                    <td>{{ $ts['department'] ?? 'N/A' }}</td>
                    <td>{{ $ts['position'] ?? 'N/A' }}</td>
                    <td>{{ $ts['month'] }}</td>
                    <td style="text-align: right;">{{ number_format($ts['regular_hours'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format($ts['overtime_hours'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format($ts['hourly_rate'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format($ts['regular_amount'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format($ts['overtime_amount'], 2) }}</td>
                    <td style="text-align: right;"><strong>{{ number_format($ts['total_amount'], 2) }}</strong></td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5">TOTAL</td>
                <td style="text-align: right;">{{ number_format($payroll['summary']['total_regular_hours'], 2) }}</td>
                <td style="text-align: right;">{{ number_format($payroll['summary']['total_overtime_hours'], 2) }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td style="text-align: right;">R {{ number_format($payroll['summary']['total_amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>This is a system-generated payroll export from NKS Capital EMS</p>
        <p>For any queries, please contact the Finance department</p>
    </div>
</body>
</html>