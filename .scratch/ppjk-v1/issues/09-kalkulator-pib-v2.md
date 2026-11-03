# 09: Kalkulator PIB v2 (per Seri Barang)

**What to build:** Customs Specialist menghitung pungutan per Seri Barang dengan rumus terkunci: kurs pajak (input manual mingguan Rabu–Selasa + nomor KMK, per mata uang), asuransi fallback 0,5%×FOB/CFR dengan basis tercatat (dalam negeri = 0), BM dengan pembulatan ke atas ribuan, Nilai Impor = CIF+BM+pungutan lain, PPN formal 12% × DPP 11/12 (flag barang_mewah per seri), PPh 22 urutan Lamp. A → B → 0,5% (dgn API) → 0,25% → umum 2,5%/7,5% → lelang 7,5%, PPnBM dari Nilai Impor dengan aturan ambang (PV-6), Total = BM+PPN+PPh22+PPnBM+pungutan lain. UI wajib menampilkan DPP per seri. Struktur data mengikuti isian PIB CEISA (header + seri).

**Blocked by:** 04, 05.

**Status:** resolved

Sumber: §3.2–3.5, Q28, Q30, Q32, ADR 0008; test vector dari `verifikasi-jdih-pv.md`.

- [x] Test golden lolos untuk: barang umum (efektif 11%), mewah (12%), tiap cabang PPh 22 termasuk lelang, pembulatan ribuan ke atas per komponen per seri
- [x] CIF Rupiah disimpan presisi penuh di draf (tanpa pembulatan prematur)
- [x] Item master berstatus Perlu Verifikasi memunculkan peringatan di draf; sumber Kurs Pajak + KMK tersimpan
- [x] Fallback asuransi menyimpan basis yang dipakai

## Catatan implementasi

1. **Service `PibCalculator`** menghitung per Seri Barang (§3.2) dengan pembulatan statutori ke atas ribuan (KMK 584/1996).
2. **Master data 4 tabel**: MFN, FTA, PPh22 rules, LuxuryGoods — semua berversi + status verifikasi (ADR 0008).
3. **Kurs Pajak (NDPBM)** dari Kemenkeu via API; fallback manual + Excel; tersimpan di `tax_exchange_rates`.
4. **Seed PPh 22** 214 pos HS dari CSV resmi PMK 51/2025 (Lampiran A & B) termuat otomatis via `LegalTariffSeeder`.
4. **FTA**: 9 skema aktif (IEU-CEPA belum berlaku per Nov 2026).