<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyajian file privat lewat endpoint ber-Gate (HARDENING 2026-11-04).
 *
 * File upload (dokumen Job, bukti milestone, POD Surat Jalan) disimpan di
 * disk 'local' (storage/app/private) — BUKAN 'public' — sehingga tidak ada
 * URL yang bisa ditebak untuk mengunduhnya tanpa login. Satu-satunya jalan
 * adalah endpoint download yang memeriksa Gate view Job + kepemilikan
 * dokumen terhadap Job itu.
 *
 * Fallback ke disk 'public' hanya untuk file LAMA yang terlanjur tersimpan
 * di sana sebelum migrasi ini: file lama tetap bisa diunduh lewat endpoint
 * ber-Gate, tetapi URL /storage/... langsungnya harus dimatikan dengan
 * menghapus symlink public/storage di deployment (lihat laporan hardening).
 */
class PrivateFile
{
    public static function download(?string $path, ?string $displayName = null): StreamedResponse
    {
        if (! $path) {
            abort(404, 'File tidak ditemukan.');
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->download($path, $displayName ?? basename($path));
            }
        }

        abort(404, 'File tidak ditemukan di penyimpanan.');
    }
}
