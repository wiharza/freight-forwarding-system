# 15: Notifikasi email + Telegram + badge + log

**What to build:** Sistem mengirim alarm ke PIC Job + Finance (internal — bukan blast ke importir): pemicu H-2/H-0/H+1 per kontainer dari engine demurrage, isi email sesuai §2.6 (nomor job, kontainer, last free date, estimasi biaya, link ke job), pesan Telegram ke target chat yang dikonfigurasi per jenis alert, badge hitungan kontainer per tingkat di dashboard, dan log semua pengiriman (kapan, ke siapa, kontainer mana) supaya tidak ada yang mengklaim "tidak diberitahu".

**Blocked by:** 08.

**Status:** ready-for-agent

Sumber: §2.6, §4.4, Q8.

- [ ] Pemicu H-2/H-0/H+1 per kontainer terpicu benar (diuji dengan tanggal simulasi)
- [ ] Email + Telegram terkirim ke penerima internal sesuai konfigurasi; konten lengkap
- [ ] Badge dashboard menghitung kuning/merah dengan benar
- [ ] Setiap pengiriman tercatat di log notifikasi
