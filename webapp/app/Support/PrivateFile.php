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
    /**
     * Ekstensi yang bisa dipratinjau browser secara native (tab baru).
     * File Office (docx/xlsx/dll) TIDAK masuk daftar ini — browser tidak
     * bisa pratinjau native, dan repo ini tidak membangun konverter dokumen.
     */
    public const PREVIEWABLE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    public static function previewableExtension(?string $fileName): bool
    {
        if (! $fileName) {
            return false;
        }

        return in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), self::PREVIEWABLE_EXTENSIONS, true);
    }

    /**
     * Sajikan file untuk pratinjau inline (tab baru) — kebalikan dari
     * download() yang memaksa attachment. Ekstensi di luar daftar
     * PREVIEWABLE_EXTENSIONS ditolak 415.
     */
    public static function preview(?string $path, ?string $displayName = null): StreamedResponse
    {
        if (! $path) {
            abort(404, 'File tidak ditemukan.');
        }

        if (! self::previewableExtension($displayName)) {
            abort(415, 'Tipe file ini tidak bisa dipratinjau di browser. Unduh filenya.');
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, $displayName ?? basename($path));
            }
        }

        abort(404, 'File tidak ditemukan di penyimpanan.');
    }

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
