<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; margin: 0; padding: 16px; }
    .header { border-bottom: 2px solid #6366f1; margin-bottom: 12px; padding-bottom: 8px; }
    .header h1 { font-size: 14pt; color: #6366f1; margin: 0 0 4px; }
    .header .meta { font-size: 8pt; color: #64748b; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #6366f1; color: #fff; padding: 5px 6px; text-align: left; font-size: 8pt; }
    td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; font-size: 8pt; }
    tr:nth-child(even) td { background: #f8fafc; }
    .footer { margin-top: 12px; font-size: 7pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
</style>
</head>
<body>
<div class="header">
    <h1>{{ $title ?? 'Report' }}</h1>
    <div class="meta">
        @if(!empty($tenant)) {{ $tenant->name }}@if(!empty($tenant->tin)) &nbsp;|&nbsp; TIN: {{ $tenant->tin }}@endif &nbsp;|&nbsp; @endif
        Generated: {{ $generated_at ?? now()->format('d M Y H:i') }}
    </div>
</div>
<table>
    <thead>
        <tr>@foreach($headers ?? [] as $h)<th>{{ $h }}</th>@endforeach</tr>
    </thead>
    <tbody>
        @forelse($rows ?? [] as $row)
        <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
        @empty
        <tr><td colspan="{{ count($headers ?? []) }}" style="text-align:center;color:#94a3b8">No data</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">SmartStock ERP — Confidential</div>
</body>
</html>
