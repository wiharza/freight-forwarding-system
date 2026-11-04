<?php

/**
 * Fitur → peran yang boleh (matriks peran × fitur, §2.5 spesifikasi).
 * Sumber kebenaran izin — dipakai Gate::define di AppServiceProvider.
 * Peran jamak diperbolehkan; izin = union peran yang dipegang user.
 * Maker-checker tidak diatur di sini — itu per user ID (ADR 0006).
 * Admin BUKAN superuser: hanya fitur yang dinyatakan eksplisit di bawah.
 */
return [
    'master_data' => ['admin'],
    'user_management' => ['admin'],

    'job_entry' => ['cs'],

    'calculator_pib' => ['customs_specialist'],
    'approve_snapshot' => ['customs_specialist'],
    'override_fta' => ['customs_specialist'],
    'fta_form' => ['cs', 'customs_specialist'],

    'job_assignment' => ['ops'],
    'verify_milestone' => ['ops'],
    'trucking_surat_jalan' => ['ops'],

    'input_milestone' => ['field'],

    'kurs_internal' => ['finance'],
    'invoice' => ['finance'],
    'pembayaran_alokasi' => ['finance'],
    // Close & reopen (§2.3, Q11): Admin/Finance. Catatan: matriks ini
    // sebelumnya hanya mencantumkan finance untuk reopen_close — spesifikasi
    // §2.3 dan §2.5 menyebut Admin/Finance, jadi admin ditambahkan di sini.
    'close_job' => ['admin', 'finance'],
    'reopen_close' => ['admin', 'finance'],

    // Laporan: granularitas per laporan diselesaikan di tiket 16 (§7).
    'laporan_semua' => ['admin'],
    'laporan_keuangan' => ['finance'],
];
