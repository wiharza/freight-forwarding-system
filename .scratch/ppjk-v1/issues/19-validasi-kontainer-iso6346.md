# 19: Validasi nomor kontainer ISO 6346 (regex + check digit)

**What to build:** `container_number` hanya divalidasi `max:20` → salah ketik lolos dan membuat demurrage/deposit mismatch dengan data line. Tegakkan format ISO 6346: `^[A-Z]{4}\d{7}$` (4 huruf owner code + 7 digit) **plus check digit mod 11** (bobot huruf A=10 s.d. Z=38 dengan skip 11/22/33; bobot posisi 1,2,4,…,256; sisa 10 = 0). Normalisasi: trim + uppercase. Validasi hanya di layer form (controller) — data legacy Excel (§1.7) masuk via model langsung tetap diterima.

**Blocked by:** None.

**Status:** resolved

- [x] `App\Support\FormatRules::containerNumberRules()` + `iso6346CheckDigit()`
- [x] Dipakai di `JobController@store`
- [x] Test: MSKU1234562 diterima, MSKU1234567 (check digit salah) ditolak, lowercase dinormalisasi
