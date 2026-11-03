# 01: Skeleton proyek — Docker, PostgreSQL, Auth, company_id

**What to build:** Pengembang menjalankan `docker compose up` dan aplikasi hidup: layar login, dashboard shell kosong yang bisa di-install sebagai PWA di HP, dengan satu user Admin seed. Fondasi mengunci aturan-aturan yang mahal jika bolak-balik: setiap tabel bisnis membawa `company_id` sejak migrasi pertama (ADR 0001), kolom uang bertipe NUMERIC, framework Laravel + Inertia.js (React/TypeScript) + PostgreSQL + Docker (ADR 0009).

**Blocked by:** None (can start immediately).

**Status:** resolved

Sumber: §8.1, §1.4, ADR 0001, ADR 0009.

- [x] `docker compose up` menyalakan app + nginx :8080 + PostgreSQL (healthcheck); login berfungsi (`GET /` 200, `/dashboard` 302→login) dengan user seed `admin@example.local`
- [x] Inertia.js + React + TypeScript terpasang (Laravel React Starter Kit); manifest PWA `public/manifest.webmanifest` + service worker `public/sw.js` + registrasi di `app.blade.php`
- [x] Migrasi `companies` + `company_id` di `users` sejak awal; trait `BelongsToCompany` (auto-fill creating + global scope per company) + `CompanyContext` singleton terdaftar
- [x] Macro migrasi `Blueprint::money()` = decimal(18,4) + test `tests/Unit/MoneyMacroTest.php` (PHPUnit — starter kit ternyata PHPUnit murni, bukan Pest)
- [x] Test suite hijau: **26 passed, 0 failed**; `.env.example` diselaraskan ke PostgreSQL tanpa rahasia (DEBUG-off produksi dikunci di tiket 17)
- [x] `README.md` (root) berisi cara jalan dev

## Catatan implementasi (penting untuk tiket berikutnya)

1. **Tidak perlu PHP/Composer lokal** — semua PHP via Docker (artisan: `docker compose exec app php ...`; scaffold via image `composer:2`). Node 24 di host untuk build Vite.
2. **Runtime PHP 8.4** (composer.lock di-resolve terhadap platform 8.4 — Dockerfile `php:8.4-fpm-alpine`).
3. **FPM dev berjalan sebagai root** (`--allow-to-run-as-root`) karena bind mount Windows tidak menghormati chmod untuk www-data. Image produksi (tiket 17) membake file + kembali ke www-data.
4. **Self-registration dihapus** (routes/auth.php, controller, test, link UI): sistem internal PPJK — user dibuat Admin (§2.5). Manajemen user = tiket 02.
5. **Konvensi test = PHPUnit 11** (class + method `test_*` + atribut `#[Test]`); `tests/Pest.php` adalah template mati bawaan (Pest tidak terinstall) — jangan tulis test Paku Pest.
6. `composer.lock` harus selalu complete — kalau container composer mati mendadak, `vendor/` bisa setengah jadi (gejala: autoload.php hilang).
