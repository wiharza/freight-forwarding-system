# 14: Invoice 3 blok + pembayaran + alokasi 2-langkah + Batas Approve

**What to build:** Finance menerbitkan invoice dari ledger: blok (a) pungutan negara dari Snapshot SPT PIB (PPN Impor bukan PPN Keluaran PPJK; angka sudah dibulatkan statutori), blok (b) biaya pihak ketiga dengan DPP & PPN faktur vendor apa adanya (0 dengan alasan bila tanpa PPN), blok (c) jasa + PPN 12%×DPP 11/12 dari nilai jasa (tarif dari master berversi); flag "kena PPN jasa" per baris; nomor berurutan tanpa lompatan; terkunci setelah approve; koreksi via nota kredit/debit; faktur pajak dicatat nomornya. Approve memakai Batas Approve per user Finance — dihitung DI LUAR blok pungutan; melewati batas → user Finance lain; Direktur tanpa batas tak boleh self-approve. Pembayaran dicatat (tanpa auto-match bank, tanpa denda telat); alokasi 2-langkah manual per baris dengan saran urutan (pungutan→pihak ketiga→jasa) + alasan penyimpangan; status baris Belum dibayar/Dibayar sebagian/Lunas; saklar blokir per importir dengan override; eksposur per importir; close mensyaratkan settlement uang muka = 0.

**Blocked by:** 11, 12, 13.

**Status:** claimed

Sumber: §6.3–6.5, §2.3, Q17, Q18, Q22, Q31, Q33, ADR 0004, ADR 0007.

- [ ] Angka blok (a) identik dengan Snapshot (tidak dihitung ulang); blok (c) menyimpan DPP Nilai Lain 11/12
- [ ] Batas approve routing benar; maker-checker menolak self-approve
- [ ] Alokasi menolak melebihi sisa baris / total pembayaran; penyimpangan urutan beralasan tercatat; sisa → Titipan Dana
- [ ] Saklar blokir per importir berfungsi dengan override beralasan
- [ ] Close job terblokir bila settlement uang muka ≠ 0 (syarat #5)
