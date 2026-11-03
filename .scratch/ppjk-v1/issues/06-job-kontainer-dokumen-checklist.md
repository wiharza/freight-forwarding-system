# 06: Job + Kontainer + Dokumen Asal + checklist berfase + status dasar

**What to build:** CS membuat Job (1 Importir + 1 B/L, 1..N Kontainer dengan nomor/ukuran/tipe/seal/berat, plus `referensi_excel_lama` & `tanggal_cutover`), mengunggah Dokumen Asal, dan mengelola checklist 3 fase (Pra-Filing / Pasca-Filing / Pasca-Delivery) dengan status item Belum ada/Diupload/Diverifikasi. "Dokumen Lengkap" dihitung otomatis dari fase Pra-Filing yang semua itemnya Diverifikasi; Form FTA berstatus tidak berlaku bila tak klaim. Status Progres mulai terhitung (Draft → Dokumen Lengkap) dengan Problem Flag terpisah (Hold/Dispute/Dokumen Bermasalah). Ops menugaskan Field ke job.

**Blocked by:** 02, 03.

**Status:** resolved

Sumber: §2.1, §2.3, §2.4, §2.7, Q10, Q11, Q21.

- [x] Job 1..N Kontainer lengkap fieldnya; upaya job LCL ditolak/diberi peringatan eksplisit (`is_fcl = false` ditolak dengan pesan: *"Sistem v1 hanya melayani FCL. LCL di luar scope (§1.2, Q2, Q10)."*)
- [x] Checklist berfase + override per Importir (`importir_checklist_overrides` dihormati); Form FTA otomatis tidak berlaku jika `claim_fta = false`
- [x] Status Progres dihitung dari data (`Job::recomputeStatus()`) menjadi "Dokumen Lengkap" setelah seluruh dokumen Pra-Filing berstatus diverifikasi; Problem Flag terpisah (`jobs.problem_flag` dengan alasan tercatat di audit trail)
- [x] Field hanya melihat/menginput job yang ditugaskan kepadanya (`JobPolicy` + `Job::scopeVisibleTo` + `jobs.assign-field`)
- [x] UI Lengkap: Daftar Job dengan filter status/flag/importir (`resources/js/pages/jobs/index.tsx`), Entri Job FCL dinamis 1..N Kontainer (`create.tsx`), Detail Job & Manajemen Dokumen 3 Fase (`show.tsx`)
- [x] Test suite: 72 passed (214 assertions) di `JobAndChecklistTest`

## Catatan implementasi

1. Entitas `Container` menyimpan nomor, ukuran (20/40/45), tipe (Dry/Reefer/High Cube/Open Top), seal, dan berat kotor dalam kg (`NUMERIC(18, 4)`).
2. Dokumen dikelompokkan ke dalam 3 fase: `pra_filing` (Invoice, Packing List, BL, Form FTA), `pasca_filing` (DO, SPPB), dan `pasca_delivery` (Bukti Gate-Out, Bukti Gate-In, POD).
3. Verifikasi dokumen hanya dapat dilakukan oleh peran yang berwenang (Ops/Admin via `can:verify_milestone`), dan setiap aksi diverifikasi memicu kalkulasi ulang status progres job.
