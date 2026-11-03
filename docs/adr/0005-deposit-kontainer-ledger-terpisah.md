# 0005 — Deposit kontainer di ledger terpisah, per kontainer

Konteks: deposit kontainer adalah dana jaminan refundable — bukan biaya — dan waktu kembali tiap kontainer bisa berbeda. Keputusan: dicatat per kontainer (bukan per job) dengan lifecycle Ditahan / Dikembalikan penuh / Dikembalikan sebagian / Hangus sebagian / Hangus penuh; hanya porsi hangus yang diposting ke ledger biaya job, sebesar porsi itu, dengan bukti debit note dari line; sisa deposit yang kembali tidak pernah masuk invoice. Total per job hanyalah tampilan agregat. Alternatif yang ditolak: deposit dicatat sebagai biaya lalu dikredit kembali (mencemari real-cost) atau dicatat per job.

Konsekuensi: Deposit Kontainer ≠ Titipan Dana (dua pot dana berbeda); laporan biaya job tidak pernah memuat deposit yang masih Ditahan; arus kas line/depot dapat direkonsiliasi per kontainer.
