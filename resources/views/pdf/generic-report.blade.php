<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #1a56db; padding-bottom: 10px; }
        .header h1 { color: #1a56db; margin: 0; font-size: 20px; }
        .header p { margin: 3px 0; color: #666; font-size: 10px; }
        .meta { background: #f3f4f6; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .meta table { width: 100%; }
        .meta td { padding: 3px 6px; font-size: 10px; }
        .meta td:first-child { font-weight: bold; color: #4b5563; }
        .section { margin: 15px 0; page-break-inside: avoid; }
        .section h2 { color: #1a56db; font-size: 13px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; margin-bottom: 8px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .data-table th { background: #1a56db; color: white; padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; }
        .data-table td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        .data-table tr:nth-child(even) { background: #f9fafb; }
        .kv-table { width: 100%; }
        .kv-table td { padding: 4px 6px; font-size: 10px; }
        .kv-table td:first-child { font-weight: bold; color: #4b5563; width: 40%; }
        .stat-grid { display: table; width: 100%; margin: 8px 0; }
        .stat { display: table-cell; padding: 8px; text-align: center; background: #eff6ff; border-radius: 4px; margin: 4px; width: 25%; }
        .stat .label { font-size: 9px; color: #666; text-transform: uppercase; }
        .stat .value { font-size: 14px; font-weight: bold; color: #1a56db; }
        .footer { margin-top: 20px; font-size: 9px; color: #999; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NKS Capital (Pty) Ltd</h1>
        <p>{{ $title }}</p>
        <p>Generated: {{ $generated_at }} by {{ $generated_by }}</p>
    </div>

    @if(!empty($filters))
        <div class="meta">
            <table>
                @foreach($filters as $k => $v)
                    <tr>
                        <td>{{ ucfirst(str_replace('_', ' ', $k)) }}:</td>
                        <td>{{ $v }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @foreach($data as $sectionKey => $sectionValue)
        <div class="section">
            <h2>{{ ucfirst(str_replace(['_', '.'], ' ', $sectionKey)) }}</h2>

            @if(is_array($sectionValue))
                @php
                    $isAssoc = array_keys($sectionValue) !== range(0, count($sectionValue) - 1);
                @endphp

                @if($isAssoc)
                    {{-- Key-value display --}}
                    <table class="kv-table">
                        @foreach($sectionValue as $k => $v)
                            <tr>
                                <td>{{ ucfirst(str_replace(['_', '.'], ' ', $k)) }}</td>
                                <td>
                                    @if(is_scalar($v) || is_null($v))
                                        {{ is_null($v) ? '—' : $v }}
                                    @else
                                        {{ json_encode($v) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    {{-- Table display --}}
                    @if(!empty($sectionValue) && is_array(reset($sectionValue)))
                        <table class="data-table">
                            <thead>
                                <tr>
                                    @foreach(array_keys(reset($sectionValue)) as $h)
                                        <th>{{ ucfirst(str_replace(['_', '.'], ' ', $h)) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sectionValue as $row)
                                    <tr>
                                        @foreach($row as $v)
                                            <td>{{ is_scalar($v) || is_null($v) ? ($v ?? '—') : json_encode($v) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <ul>
                            @foreach($sectionValue as $v)
                                <li>{{ is_scalar($v) ? $v : json_encode($v) }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            @else
                <p>{{ $sectionValue }}</p>
            @endif
        </div>
    @endforeach

    <div class="footer">
        <p>NKS Capital EMS — Report generated on {{ $generated_at }}</p>
    </div>
</body>
</html>