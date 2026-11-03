# 0001 — Skema database siap multi-tenant meski deploy internal tool

Konteks: pemilik sistem adalah satu perusahaan PPJK (keputusan Ronda 1), tetapi menginginkan opsi menjadi SaaS di masa depan. Keputusan: seluruh tabel data bisnis membawa `company_id` sejak migrasi pertama dan isolasi ditegakkan di layer query. Alternatif yang ditolak: skema single-tenant polos (rombak besar bila naik kelas ke SaaS) dan multi-tenant penuh hari ini (kompleksitas manajemen tenant/billing yang belum dibutuhkan).

Konsekuensi: tidak ada tabel data bisnis tanpa `company_id`; master referensi publik (tarif, HS code, kurs) boleh global dan di-cache per company. Satu-satunya pemilik data di v1 adalah company milik perusahaan itu sendiri.
