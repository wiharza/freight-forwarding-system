# 11: Snapshot PIB immutable + Lembar Kerja PIB PDF

**What to build:** Customs Specialist menyetujui kalkulasi → tersimpan Snapshot PIB immutable berversi (isi lengkap §3.7: FOB, asuransi+basis, freight, CIF per mata uang, kurs+KMK, tarif MFN/FTA, BM, Nilai Impor, DPP & PPN, PPh 22, PPnBM, pungutan lain, total, versi kalkulator, penyetuju + waktu). Revisi = versi baru dengan alasan & diff; kurs berganti → draf stale wajib approve ulang; item master berstatus Perlu Verifikasi MEMBLOKIR filing (ADR 0008). Setelah filing: nomor aju/billing code/NTPN ditempel ke versi snapshot; checklist konfirmasi importir (tanggal/channel/oleh). Lembar Kerja PIB (PDF + web) hanya dari snapshot disetujui, mencetak nomor versi, berlabel "LEMBAR KERJA — BUKAN DOKUMEN RESMI", urutan field mengikuti template CEISA berversi.

**Blocked by:** 09, 10.

**Status:** ready-for-agent

Sumber: §3.7, §3.9, Q20, Q26, ADR 0008.

- [ ] Revisi menghasilkan versi baru; versi lama terlihat dengan perbandingan; draf tidak tertimpa
- [ ] Kurs stale memaksa approve ulang; tidak ada pemakaian kurs baru diam-diam
- [ ] Item status Perlu Verifikasi memblokir filing; status T/S lolos dengan dasar hukum tercatat
- [ ] Lembar Kerja PDF = angka web = snapshot; label & nomor versi tercetak
- [ ] Nomor aju/billing code/NTPN menempel pada versi yang difile (bukan job)
