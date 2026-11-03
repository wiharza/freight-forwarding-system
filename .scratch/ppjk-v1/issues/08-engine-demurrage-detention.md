# 08: Engine Demurrage/Detention + lampu indikator

**What to build:** Dashboard operasional menampilkan estimasi biaya per kontainer secara live: demurrage (Hari 0 = discharge, hari terakhir = gate-out) dan detention (Hari 0 = gate-out, akhir = gate-in) — tanggal gate-out tidak dihitung ganda. Estimasi memakai tarif & kurs hari ini; biaya final terkunci saat gate-in memakai tarif yang berlaku pada periode keterlambatan. Last free date dihitung dari konfigurasi line. Lampu hijau/kuning/merah per kontainer dengan pemicu H-2/H-0/H+1; cron harian 00:00 + recompute tiap perubahan milestone.

**Blocked by:** 04, 07.

**Status:** resolved

Sumber: §4.1–4.4, Q9, Q11.

- [x] Test golden lolos: batas tier (e.g. 5 hari telat @ 20ft -> USD 235.00), offset H+0/H+1, kalender penuh vs hari kerja (`calendar_type = working` tidak menghitung akhir pekan), mode gabungan (`mode = gabungan`), dan tarif ber-versi (`DemurrageCalculatorTest`)
- [x] Anti double-count gate-out terverifikasi: gate-out adalah hari terakhir demurrage SEKALIGUS Hari 0 detention (terbukti di test `anti_double_count_gate_out_tidak_dihitung_ganda_pada_demurrage_dan_detention`)
- [x] Estimasi berjalan vs final terbedakan jelas di UI: label *"Estimasi Berjalan (Live)"* vs *"Biaya Terkunci (Final)"* setelah seluruh kontainer gate-in (`resources/js/pages/jobs/show.tsx`)
- [x] Lampu indikator hijau (aman > 3 hari), kuning (warning 1-3 hari / H-2, H-0), dan merah (overdue) live per kontainer
- [x] Cron scheduler harian jam 00:00 (`app:recalculate-demurrage` + `routes/console.php` `dailyAt('00:00')`)
- [x] Test suite: 84 passed (246 assertions) di `DemurrageEngineTest`

## Catatan implementasi

1. Service `App\Services\DemurrageCalculator` menghitung hari berbasis tanggal murni (`startOfDay()`) sehingga durasi tidak terpotong oleh jam kejadian (misal 14:00 vs 11:00).
2. Denda dihitung secara dinamis dari tabel `line_tariffs` aktif per ukuran kontainer tanpa ada angka tarif default yang di-hardcode.
3. Estimasi denda dalam valas (USD) dikonversi live ke IDR menggunakan `InternalExchangeRate::resolveRate()` resmi hari tersebut.
