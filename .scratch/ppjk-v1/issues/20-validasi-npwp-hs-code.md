# 20: Validasi NPWP 15/16 digit + kode HS 8/10 digit

**What to build:** NPWP hanya `max:20` → dedup importir per NPWP (§1.7) bisa gagal karena format kacau. Tegakkan: setelah dibersihkan dari tanda baca, harus 15 digit (NPWP lama, PMK 24/2010) atau 16 digit (NIK-integrasi, PMK 112/2022). Kode HS di item kalkulator PIB dan master legal (MFN/FTA) hanya `max:12` → salah HS berarti salah BM/PPh 22 (regulasi). Tegakkan: 8 digit (BTKI) atau 10 digit (BTKI + suffix CEISA), numerik murni.

**Blocked by:** None.

**Status:** resolved

- [x] `FormatRules::npwpRules()` di store importir (normalisasi digit-only sebelum unique check)
- [x] `FormatRules::hsCodeRules()` di draf PIB (`storeDraft`) + master MFN/FTA
- [x] Test: NPWP 15/16 digit diterima, huruf ditolak; HS '0301.11.00' ditolak, '03011111' & '0301111100' diterima
