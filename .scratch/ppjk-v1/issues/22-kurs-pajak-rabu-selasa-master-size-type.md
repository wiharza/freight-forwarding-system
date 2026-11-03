# 22: Kurs Pajak Rabu→Selasa + size/type kontainer dari master

**What to build:** (a) `storeTaxRate` menerima rentang tanggal bebas, padahal PV-3: periode NDPBM Rabu s.d. Selasa. Tegakkan `valid_from` harus hari Rabu dan `valid_to` = `valid_from` + 6 hari. (b) `containers.*.size` di-hardcode `Rule::in(['20','40','45'])` dan `type` free-text, padahal ada master `container_types` — validasi pasangan (size, type) dari master per company agar form tidak menyimpang dari master.

**Blocked by:** None.

**Status:** resolved

- [x] Validasi Rabu + rentang 6 hari di `storeTaxRate`
- [x] Validasi (size, type) terhadap `container_types` per company di `JobController@store`
- [x] Test: Senin ditolak, Rabu+6 hari diterima; tipe di luar master ditolak
