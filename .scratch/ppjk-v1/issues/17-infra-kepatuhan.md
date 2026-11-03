# 17: Infra kepatuhan — staging, backup, runbook insiden, residensi

**What to build:** Lingkungan staging terpisah (TANPA data pribadi riil importir), deployment ke cloud managed region Indonesia dengan DPA + komitmen residensi termasuk lokasi backup, backup harian otomatis mencakup database + file bukti dengan retensi 30 hari, prosedur uji restore kuartalan (dijalankan sekali dan didokumentasikan), runbook insiden kebocoran 3×24 jam (deteksi → containment → pelestarian log → kontak → notifikasi; lembaga ditulis generik per PV-12), DEBUG off, database tak terekspos publik, HTTPS wajib.

**Blocked by:** 01 *(validasi penuh setelah 16)*.

**Status:** ready-for-agent

Sumber: §8, Q25, Q34, PV-11, PV-12.

- [ ] Staging hidup dengan data sintetis; produksi terpisah
- [ ] Backup harian DB+file terbukti berjalan; drill restore kuartal pertama terdokumentasi
- [ ] Runbook insiden 3×24 jam ada sebagai dokumen hidup
- [ ] Checklist residensi provider (DPA + lokasi backup) terisi dan diarsipkan
- [ ] HTTPS aktif; DB tidak publik; DEBUG off di staging & produksi
