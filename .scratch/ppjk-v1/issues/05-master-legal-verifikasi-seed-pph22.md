# 05: Empat master legal berversi + status verifikasi + seed PPh 22

**What to build:** Admin/Customs Specialist mengelola empat master legal effective-dated: (a) MFN per HS (BTKI 2022 + delta PMK 62/2025), (b) preferensi FTA per skema per HS (9 skema aktif; IEU-CEPA belum), (c) mapping HS→lampiran PPh 22 — **seed 215 pos dari `docs/research/seed-pph22-lampiran.csv`**, (d) dua sub-daftar barang mewah (PPN & PPnBM) dengan ambang Nilai Impor (PV-6: USD 30k/50k). Tiap item membawa status Terverifikasi/Perlu Verifikasi sesuai laporan `docs/research/verifikasi-jdih-pv.md`; update wajib alasan + dasar hukum + approval orang kedua (maker-checker).

**Blocked by:** 02 *(paralel dengan 03–04)*.

**Status:** resolved

Sumber: §3.6, §3.3, Q29, Q30, ADR 0008, verifikasi-jdih-pv.md.

- [x] Keempat master ada di skema + UI; seed CSV PPh 22 termuat dan bisa dicari per HS (`MfnTariff`, `FtaTariff`, `Pph22Rule`, `LuxuryGood` + `LegalTariffSeeder` + `LegalTariffController`)
- [x] Status verifikasi per item tersimpan; item Perlu Verifikasi diberi label peringatan saat dipakai draf (`verification_status: terverifikasi | perlu_verifikasi` sesuai ADR 0008)
- [x] Update tarif wajib alasan + dasar hukum + penyetuju ≠ pengusul (`MfnTariff::approve`, `FtaTariff::approve` menegakkan `MakerChecker::assertApproverDiffers`)
- [x] Aturan ambang PPnBM didukung engine (`LuxuryGood::checkHs` mendukung ambang USD 30.000 / USD 50.000 per PV-6); 9 skema FTA terdaftar tanpa IEU-CEPA (`FtaTariff::SCHEMES`)
- [x] UI Admin tabulasi: MFN Tariffs, Preferensi FTA, PPh 22 Rules, Luxury Goods (`resources/js/pages/admin/master/*`)
- [x] Test suite: 66 passed (191 assertions) di `LegalTariffTest`

## Catatan implementasi

1. File CSV resmi `docs/research/seed-pph22-lampiran.csv` (214 pos tarif HS) disalin ke `database/data/` dan di-seed otomatis via `LegalTariffSeeder`.
2. Model `Pph22Rule::matchHs()` mendukung pencarian exact 8-digit maupun prefix pos 4-digit (Bab.Pos seperti 33.04 atau 84.71).
3. `LuxuryGood::checkHs()` membedakan barang mewah PPN (kena 12% penuh tanpa DPP 11/12) dan barang kena PPnBM berdasarkan ambang Nilai Impor dalam USD.
