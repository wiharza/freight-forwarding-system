# 10: FTA gate-keeper

**What to build:** Saat mau filing, sistem memeriksa Form FTA yang metadata-nya terikat ke baris HS PIB (nomor, tanggal terbit, negara, masa berlaku, HS di Form, pelabuhan muat): kecocokan HS memakai prefix 6 digit, negara/pelabuhan muat, dan masa berlaku terhadap tanggal filing. Mismatch mana pun = peringatan keras: filing tak bisa lanjut kecuali Customs Specialist override dengan alasan tercatat + lampiran (masuk audit trail). Form belum ada → decision log (siapa/kapan/alasan/risiko masa berlaku) dan job ditandai "FTA tidak diklaim — MFN" yang tak bisa diam-diam diubah di versi PIB yang sama.

**Blocked by:** 06, 09.

**Status:** ready-for-agent

Sumber: §3.8, Q19.

- [ ] Prefix 6 digit: Form 6 digit cocok dengan PIB 8 digit pada prefix yang sama
- [ ] Tiga validasi (HS, negara/pelabuhan, masa berlaku) memicu peringatan keras; override memerlukan alasan + lampiran + audit
- [ ] Decision log MFN tercatat; penanda MFN tidak bisa diganti FTA diam-diam pada versi yang sama
