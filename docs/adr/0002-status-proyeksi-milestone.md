# 0002 — Status job sebagai proyeksi milestone, bukan kolom manual

Konteks: kolom `status` yang diubah manual hampir pasti basi dan tidak akurat pada volume 100–300 job/bulan. Keputusan: status dihitung dari milestone (fakta bertanggal berbukti), di-cache untuk dashboard dan selalu dihitung ulang saat ada perubahan milestone; edit status manual tidak ada di sistem; close/reopen dibatasi peran Admin/Finance dengan alasan dan tercatat di audit trail. Alternatif yang ditolak: kolom status manual — murah di awal, mahal pada kebenaran data.

Konsekuensi: perlu engine perhitungan status dan daftar status yang dikunci eksplisit di spesifikasi; status parsial ("Gate-Out Sebagian") lahir dari agregat per kontainer; masalah direpresentasikan sebagai Problem Flag terpisah, bukan status.
