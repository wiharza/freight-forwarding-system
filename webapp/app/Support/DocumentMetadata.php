<?php

namespace App\Support;

use App\Models\FtaForm;
use App\Models\Job;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Skema isian data terstruktur per jenis dokumen (§2.4, Q21).
 *
 * Kolom `metadata` (JSON) di JobDocument sudah ada sejak awal, dan
 * uploadDocument sudah menerima + menyimpan array metadata — tapi UI
 * tidak pernah mengirimnya, sehingga baris dokumen hanya membawa
 * tipe/fase/status/file. Kelas ini satu-satunya sumber kebenaran untuk:
 * daftar isian per jenis, validasi, ringkasan satu baris, dan prefill.
 *
 * CERMIN DI UI: resources/js/pages/jobs/show.tsx (DOC_METADATA_FIELDS)
 * memakai label + tipe input yang sama. Bila menambah jenis/isian,
 * ubah di kedua tempat.
 *
 * Aturan: isian bersifat opsional saat upload (upload tanpa metadata
 * tetap sah, seperti sebelumnya); bila diisi, nilai harus lolos
 * validasi tipe; kunci di luar skema ditolak 422.
 */
class DocumentMetadata
{
    /**
     * type: text | date | number | integer | select
     * label dipakai di form UI dan di ringkasan.
     */
    public const SCHEMAS = [
        'invoice' => [
            'nomor_invoice' => ['label' => 'No. Invoice', 'type' => 'text'],
            'tanggal_invoice' => ['label' => 'Tgl. Invoice', 'type' => 'date'],
            'nilai' => ['label' => 'Nilai', 'type' => 'number'],
            'mata_uang' => ['label' => 'Mata Uang', 'type' => 'select', 'options' => ['IDR', 'USD']],
        ],
        'packing_list' => [
            'jumlah_koli' => ['label' => 'Jumlah Koli', 'type' => 'integer'],
            'berat_kotor_kg' => ['label' => 'Berat Kotor (kg)', 'type' => 'number'],
            'volume_m3' => ['label' => 'Volume (m³)', 'type' => 'number'],
        ],
        'bl' => [
            'nomor_bl' => ['label' => 'No. B/L', 'type' => 'text'],
            'tanggal_bl' => ['label' => 'Tgl. B/L', 'type' => 'date'],
        ],
        'form_fta' => [
            'nomor_form' => ['label' => 'No. Form', 'type' => 'text'],
            'skema' => ['label' => 'Skema', 'type' => 'text'],
            'negara_asal' => ['label' => 'Negara Asal', 'type' => 'text'],
            'tanggal_terbit' => ['label' => 'Tgl. Terbit', 'type' => 'date'],
            'tanggal_berlaku' => ['label' => 'Tgl. Berlaku', 'type' => 'date'],
        ],
        'do' => [
            'nomor_do' => ['label' => 'No. DO', 'type' => 'text'],
            'tanggal_terbit' => ['label' => 'Tgl. Terbit', 'type' => 'date'],
        ],
        'sppb' => [
            'nomor_sppb' => ['label' => 'No. SPPB', 'type' => 'text'],
            'tanggal_sppb' => ['label' => 'Tgl. SPPB', 'type' => 'date'],
        ],
        'gate_out_proof' => [
            'tanggal' => ['label' => 'Tanggal', 'type' => 'date'],
            'keterangan' => ['label' => 'Keterangan', 'type' => 'text'],
        ],
        'gate_in_proof' => [
            'tanggal' => ['label' => 'Tanggal', 'type' => 'date'],
            'keterangan' => ['label' => 'Keterangan', 'type' => 'text'],
        ],
        'pod' => [
            'tanggal_terima' => ['label' => 'Tgl. Terima', 'type' => 'date'],
            'penerima' => ['label' => 'Penerima', 'type' => 'text'],
        ],
    ];

    public static function fieldsFor(string $type): array
    {
        return self::SCHEMAS[$type] ?? [];
    }

    /**
     * Validasi metadata terhadap skema jenis dokumen. Kunci tak dikenal
     * ditolak 422; nilai salah tipe ditolak 422. Mengembalikan metadata
     * yang sudah dinormalisasi (nilai kosong dibuang).
     */
    public static function validate(?array $metadata, string $type): array
    {
        $metadata = $metadata ?? [];
        $schema = self::fieldsFor($type);

        $unknown = array_diff(array_keys($metadata), array_keys($schema));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'metadata' => 'Isian data tidak dikenal untuk dokumen '.$type.': '.implode(', ', $unknown).'.',
            ]);
        }

        // Nilai kosong dianggap tidak diisi (bukan nol).
        $normalized = [];
        foreach ($metadata as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $normalized[$key] = $value;
        }

        $rules = [];
        foreach ($schema as $key => $def) {
            $rules['metadata.'.$key] = match ($def['type']) {
                'date' => ['nullable', 'date_format:Y-m-d'],
                'number' => ['nullable', 'numeric', 'min:0'],
                'integer' => ['nullable', 'integer', 'min:0'],
                'select' => ['nullable', 'in:'.implode(',', $def['options'] ?? [])],
                default => ['nullable', 'string', 'max:191'],
            };
        }

        Validator::make(['metadata' => $normalized], $rules)->validate();

        return $normalized;
    }

    /**
     * Ringkasan satu baris untuk tampilan
     * ("No. Invoice: INV-001 · Tgl. Invoice: 01-11-2026 · Nilai: Rp 1.000.000").
     * Null bila tidak ada metadata.
     */
    public static function summary(?array $metadata, string $type): ?string
    {
        $metadata = $metadata ?? [];
        if ($metadata === []) {
            return null;
        }

        $schema = self::fieldsFor($type);
        $parts = [];
        foreach ($schema as $key => $def) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }
            $parts[] = $def['label'].': '.self::formatValue($metadata[$key], $def, $metadata);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Nilai awal isian: data Job / Form FTA yang sudah ada, supaya tidak
     * entri ganda. Data yang sudah tersimpan di metadata menang atas
     * prefill.
     */
    public static function prefill(Job $job, string $type, array $existing = []): array
    {
        $prefill = [];

        if ($type === 'bl' && $job->bl_number) {
            $prefill['nomor_bl'] = $job->bl_number;
        }

        if ($type === 'form_fta') {
            $form = FtaForm::query()->where('job_id', $job->id)->latest('id')->first();
            if ($form) {
                $prefill = array_filter([
                    'nomor_form' => $form->form_number,
                    'skema' => $form->scheme_code,
                    'negara_asal' => $form->origin_country,
                    'tanggal_terbit' => $form->issued_at?->format('Y-m-d'),
                    'tanggal_berlaku' => $form->valid_until?->format('Y-m-d'),
                ], fn ($value) => $value !== null && $value !== '');
            }
        }

        $kept = array_filter($existing, fn ($value) => $value !== null && $value !== '');

        return array_merge($prefill, $kept);
    }

    private static function formatValue(mixed $value, array $def, array $metadata): string
    {
        return match ($def['type']) {
            'date' => self::formatDate($value),
            'number' => self::formatMoney($value, $metadata),
            'integer' => number_format((int) $value, 0, ',', '.'),
            default => (string) $value,
        };
    }

    private static function formatDate(mixed $value): string
    {
        $parsed = date_create((string) $value);

        return $parsed ? $parsed->format('d-m-Y') : (string) $value;
    }

    /**
     * Angka diformat sebagai uang bila konteksnya nilai invoice (ada
     * mata_uang di metadata yang sama); selain itu sebagai angka biasa.
     * Tidak ada aritmatika float di sini — hanya format tampilan.
     */
    private static function formatMoney(mixed $value, array $metadata): string
    {
        $number = number_format((float) $value, fmod((float) $value, 1.0) === 0.0 ? 0 : 2, ',', '.');

        if (array_key_exists('mata_uang', $metadata)) {
            return ($metadata['mata_uang'] === 'USD' ? 'USD ' : 'Rp ').$number;
        }

        return $number;
    }
}
