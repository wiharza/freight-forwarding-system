# 15: Notifikasi email + Telegram + badge + log

**What to build:** Sistem mengirim alarm ke PIC Job + Finance (internal — bukan blast ke importir): pemicu H-2/H-0/H+1 per kontainer dari engine demurrage, isi email sesuai §2.6 (nomor job, kontainer, last free date, estimasi biaya, link ke job), pesan Telegram ke target chat yang dikonfigurasi per jenis alert, badge hitungan kontainer per tingkat di dashboard, dan log semua pengiriman (kapan, ke siapa, kontainer mana) supaya tidak ada yang mengklaim "tidak diberitahu".

**Blocked by:** 08.

**Status:** resolved

Sumber: §2.6, §4.4, Q8.

- [x] Pemicu H-2/H-0/H+1 per kontainer terpicu benar (diuji dengan tanggal simulasi)
- [x] Email + badge terkirim ke penerima internal sesuai konfigurasi; konten lengkap
- [x] Badge dashboard menghitung kuning/merah dengan benar
- [x] Setiap pengiriman tercatat di log notifikasi

**Catatan implementasi (2026-11-04, branch `fitur-notifikasi`, HEAD menyusul):** v1 dibangun sesuai Q8
hasil grill — email + badge saja. Telegram TIDAK di-wire ke pemicu baru (kode lama
`TelegramConfig`/`sendTelegram` dibiarkan apa adanya, tidak dipakai alur ini);
WhatsApp fase 2. "PIC Job" = pembuat Job (`created_by_user_id`) karena tidak ada
kolom `pic_user_id` di skema. Pemicu hanya berbunyi pada tanggalnya persis
(tidak retroaktif) dan hanya untuk jam yang masih berjalan (belum final).
