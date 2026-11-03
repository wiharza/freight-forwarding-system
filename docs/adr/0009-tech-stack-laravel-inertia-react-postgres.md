# 0009 — Pilihan Stack Teknologi: Laravel, Inertia.js (React/TypeScript) PWA, PostgreSQL, Docker

Konteks: Sistem internal PPJK impor laut FCL (skala 100–300 job/bulan, 5–15 user) memiliki batas ketat (§8.1): uang desimal presisi tinggi (anti-float biner), migrasi database hidup, pengujian otomatis wajib, akses mobile PWA untuk Field dengan kemampuan simpan draf offline, scheduler harian jam 00:00, maker-checker per user ID, dan skema siap multi-tenant.

Keputusan:
1. **Backend:** Laravel (PHP 8.2+) — menyediakan Eloquent `$casts` desimal via `bcmath`, Policies/Gates untuk Maker-Checker per user ID (ADR 0006), Task Scheduler bawaan untuk alarm demurrage jam 00:00, serta sistem migrasi database dan pengujian (Pest/PHPUnit).
2. **Frontend:** Inertia.js dengan React & TypeScript — menyatukan frontend modern dan backend dalam satu repositori (monolith) tanpa boilerplate REST API/CORS/token ganda, dilengkapi service worker PWA untuk akses mobile Field di pelabuhan.
3. **Database:** PostgreSQL — kolom uang menggunakan `NUMERIC(18, 4)` untuk menjamin integritas pembulatan statutori PIB dan invoice internal.
4. **Kontainerisasi:** Docker — image OCI siap deploy ke managed container/database di Cloud Region Indonesia (kepatuhan UU PDP).

Alternatif yang ditolak:
- **Rust (Axum/Actix):** Ditolak karena over-engineering untuk traffic 10–15 transaksi/hari dan memperlambat adaptasi perubahan regulasi kepabeanan/pajak yang sering berubah.
- **Decoupled Next.js + Laravel API:** Ditolak karena menambah kompleksitas manajemen 2 repositori, CORS, dan sinkronisasi tipe data yang tidak perlu untuk tim 5–15 orang.

Konsekuensi: Pengembangan berjalan dalam satu repositori git; seluruh entitas bisnis diwajibkan menggunakan trait tenant `company_id` (ADR 0001) dan tipe kolom moneter `NUMERIC`.
