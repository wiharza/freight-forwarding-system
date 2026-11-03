# PPJK Freight Forwarding System

Sistem pengurusan impor laut untuk PPJK — spesifikasi final v2.1 di [`Spesifikasi_SOP_Sistem_Web_Forwarding.md`](Spesifikasi_SOP_Sistem_Web_Forwarding.md). Tiket build: [`.scratch/ppjk-v1/issues/`](.scratch/ppjk-v1/issues/).

**Stack (ADR 0009):** Laravel 12 · Inertia.js (React/TypeScript) PWA · PostgreSQL 17 · Docker.

## Menjalankan (dev)

```bash
docker compose up -d          # app (php-fpm) + nginx :8080 + postgres
cd webapp
npm install && npm run build  # build aset Inertia/Vite (Node di host)
cd ..
docker compose exec app php artisan migrate --seed --force
```

Buka http://localhost:8080 — login: `admin@example.local` / `password` (ganti saat go-live).

## Catatan dev

- Image `webapp/Dockerfile` menjalankan pool FPM **sebagai root** — hanya untuk dev dengan bind mount Windows yang tidak menghormati chmod. Image produksi akan membake file dan kembali ke `www-data`.
- Tidak perlu PHP/Composer lokal: semua PHP lewat container (`docker compose exec app php artisan ...`; scaffold/composer via image `composer:2`).
- Uang SELALU via macro migrasi `->money('kolom')` = NUMERIC(18,4) — float dilarang (§8.1).
