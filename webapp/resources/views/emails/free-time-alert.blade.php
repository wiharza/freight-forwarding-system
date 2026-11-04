<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Alarm Free Time {{ $chargeLabel }} {{ $triggerLabel }}</title></head>
<body style="font-family: sans-serif; line-height: 1.6; color: #111;">
<p>Yth. Bapak/Ibu,</p>

<p>
    @if ($remainingDays >= 0)
        Free Time <strong>{{ $chargeLabel }}</strong> untuk kontainer di bawah ini tinggal
        <strong>{{ $remainingDays }} hari</strong> (pemicu {{ $triggerLabel }}).
    @else
        Free Time <strong>{{ $chargeLabel }}</strong> untuk kontainer di bawah ini sudah lewat
        <strong>{{ abs($remainingDays) }} hari</strong> (pemicu {{ $triggerLabel }}).
    @endif
</p>

<table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
    <tr><td>Nomor Job</td><td><strong>{{ $job->job_number }}</strong></td></tr>
    <tr><td>Kontainer</td><td><strong>{{ $container->container_number }} ({{ $container->size }}')</strong></td></tr>
    <tr><td>Jenis biaya</td><td>{{ $chargeLabel }}</td></tr>
    <tr><td>Last Free Date</td><td><strong>{{ $freeTimeDate->format('d M Y') }}</strong></td></tr>
    <tr><td>Estimasi biaya saat ini</td><td>USD {{ number_format($costUsd, 2) }} (Rp {{ number_format($costIdr, 0, ',', '.') }})</td></tr>
    <tr><td>Per {{ $asOf->format('d M Y') }}</td><td>{{ $remainingDays >= 0 ? "sisa {$remainingDays} hari" : 'lewat ' . abs($remainingDays) . ' hari' }}</td></tr>
</table>

<p>Segera tindak lanjuti kontainer ini — lihat detail Job di sini:<br>
<a href="{{ $jobUrl }}">{{ $jobUrl }}</a></p>

<p>Salam,<br>Sistem PPJK Freight Forwarding</p>
</body>
</html>
