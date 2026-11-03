# 04: Tarif Line berversi + Kurs Internal harian

**What to build:** Admin menginput tarif demurrage/detention per shipping line — kunci: line + jenis biaya + ukuran kontainer + tier hari + tanggal berlaku — tanpa satu angka tarif pun di-hardcode; konfigurasi per line: free days (demurrage & detention terpisah), start offset H+0/H+1 per jenis, tipe kalender (kalender/kerja) per jenis, mode terpisah/gabungan. Finance menetapkan Kurs Internal resmi per hari per company per mata uang (dengan jam cutoff); riwayatnya tak bisa diedit setelah dipakai invoice. Update tarif wajib maker-checker.

**Blocked by:** 03.

**Status:** resolved

Sumber: §4.2–4.3, §6.6, Q9, Q14, Q24, ADR 0003.

- [x] Tarif Line effective-dated; versi baru tidak mengubah job yang sudah final; tidak ada angka tarif di kode/seed tanpa label dummy (`LineTariff` model + `line_tariffs` table)
- [x] Konfigurasi line lengkap: `free_days_dem/det`, offset masing-masing (`H+0`/`H+1`), tipe kalender masing-masing (`calendar`/`working`), mode (`terpisah`/`gabungan`)
- [x] Kurs Internal unik per (tanggal, company, mata uang); riwayat immutable setelah terpakai invoice (`is_locked`); flag "kurs belum resmi hari ini" untuk draf (`InternalExchangeRate::resolveRate`)
- [x] Update tarif & kurs tercatat di audit trail dengan maker-checker (`LineTariff::approve` menegakkan `MakerChecker::assertApproverDiffers`)
- [x] UI Admin Tarif Line + Konfigurasi Free Time (`resources/js/pages/admin/master/line-tariffs.tsx`)
- [x] UI Finance Kurs Internal Harian (`resources/js/pages/finance/exchange-rates.tsx`)
- [x] Test suite: 61 passed (169 assertions) di `LineTariffAndRatesTest`

## Catatan implementasi

1. `LineTariff` mendukung tiering terbuka (`day_to` null berarti hari ke-N ke atas). Status `draft` diajukan oleh pembuat, dan approval hanya sah bila dilakukan oleh user lain (ADR 0006).
2. `InternalExchangeRate` menyimpan rate presisi `NUMERIC(18, 4)` dengan proteksi model-level `is_locked` mencegah update pasca-invoice final (ADR 0003).
3. Resolver `InternalExchangeRate::resolveRate` membedakan `is_official_today = true` (kurs persis hari tersebut) vs fallback draf (`is_official_today = false`, mengambil kurs resmi terakhir sebelumnya).
