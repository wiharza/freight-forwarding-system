Yth. Bapak/Ibu,

@if ($remainingDays >= 0)
Free Time {{ $chargeLabel }} untuk kontainer di bawah ini tinggal {{ $remainingDays }} hari (pemicu {{ $triggerLabel }}).
@else
Free Time {{ $chargeLabel }} untuk kontainer di bawah ini sudah lewat {{ abs($remainingDays) }} hari (pemicu {{ $triggerLabel }}).
@endif

Nomor Job: {{ $job->job_number }}
Kontainer: {{ $container->container_number }} ({{ $container->size }}')
Jenis biaya: {{ $chargeLabel }}
Last Free Date: {{ $freeTimeDate->format('d M Y') }}
Estimasi biaya saat ini: USD {{ number_format($costUsd, 2) }} (Rp {{ number_format($costIdr, 0, ',', '.') }})
Per {{ $asOf->format('d M Y') }}: {{ $remainingDays >= 0 ? "sisa {$remainingDays} hari" : 'lewat ' . abs($remainingDays) . ' hari' }}

Segera tindak lanjuti kontainer ini — lihat detail Job di sini:
{{ $jobUrl }}

Salam,
Sistem PPJK Freight Forwarding
