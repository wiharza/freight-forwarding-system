# 16: Enam laporan wajib + ekspor Excel

**What to build:** Laporan v1 lengkap dengan akses per pemetaan §7: (a) daftar Job + Status Progres + Problem Flag + countdown Free Time per kontainer; (b) eksposur per Importir (bruto + kolom Titipan Dana terpisah); (c) piutang & aging dari tanggal jatuh tempo (bucket 0–30/31–60/61–90/>90); (d) estimasi vs aktual (kalkulator vs SPT PIB, dan per baris — IDR & % + alasan selisih); (e) denda demurrage/detention per line × ukuran × periode + rata-rata waktu ke gate-out/gate-in; (f) arus deposit (termasuk deposit ditahan lama sebagai tindak lanjut). Semua bisa difilter periode/Importir/line; ekspor Excel untuk (b)(c)(d)(f). Akses: Finance (b)(c)(d)(f); Ops (a)(e); Customs Specialist bagian PIB dari (d); Field tidak ada.

**Blocked by:** 08, 14.

**Status:** ready-for-agent

Sumber: §7, Q27.

- [ ] Keenam laporan sesuai definisi terkunci; filter berfungsi
- [ ] Aging dari tanggal jatuh tempo (bukan tanggal invoice); bucket benar
- [ ] Eksposur bruto ≠ pengurangan Titipan Dana diam-diam (kolom terpisah)
- [ ] Ekspor Excel (b)(c)(d)(f) berfungsi; akses per peran ditegakkan
