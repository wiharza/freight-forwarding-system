# AGENTS.md — PPJK Freight Forwarding System

Instruksi untuk agen kerja di repo ini. Baca `docs/agents/domain.md` sebelum menyentuh kode.

## Agent skills

### Issue tracker

Tiket hidup sebagai file markdown lokal di `.scratch/<feature-slug>/issues/` (satu file per tiket, tanpa remote). See `docs/agents/issue-tracker.md`.

### Triage labels

Lima label kanonik default (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`), ditulis sebagai baris `Status:` di file tiket. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `GLOSSARY.md` di root + `docs/adr/` (0001–0009) + spesifikasi induk `Spesifikasi_SOP_Sistem_Web_Forwarding.md` (v2.1). See `docs/agents/domain.md`.

## Aturan keras proyek ini

1. **Istilah mengikuti `GLOSSARY.md`** — Shipment/Consignee/Task/dollar_rate dilarang (lihat daftar _Avoid_ di glossary).
2. **Sumber hukum tunggal**: `docs/research/regulasi-kepabeanan-dan-pajak.md` + `verifikasi-jdih-pv.md`. Logika regulasi dari repo publik/luar dilarang (Lampiran D spesifikasi).
3. **Item master legal berstatus "Perlu Verifikasi" diblokir di filing** (ADR 0008) — draf boleh dengan peringatan.
4. **Uang = desimal** (NUMERIC di PostgreSQL, cast desimal di Laravel) — float dilarang.
5. **Maker-checker per user ID** — pembuat ≠ penyetuju, berlaku walau peran jamak (ADR 0006).
6. **Semua tabel bisnis membawa `company_id`** sejak migrasi pertama (ADR 0001).
7. **Tidak ada fork dari repo referensi** — pola saja (Lampiran D).
