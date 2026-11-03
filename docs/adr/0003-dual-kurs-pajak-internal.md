# 0003 — Dua kurs terpisah: Kurs Pajak (PIB) vs Kurs Internal (tagihan)

Konteks: kurs pajak (NDPBM, otomatis dari Kemenkeu, untuk PIB) secara alamiah berbeda dari kurs komersial perusahaan (diset Finance per hari per company dengan jam cutoff, untuk tagihan importir). Keputusan: dua jalur kurs terpisah dengan snapshot immutable — invoice menyimpan jumlah valas asli + kurs + hasil IDR; riwayat kurs internal tidak dapat diedit setelah dipakai invoice; estimasi berjalan memakai kurs hari ini, invoice final mengunci kurs tanggal invoice. Alternatif yang ditolak: satu kurs untuk semuanya (selisih tidak bisa diaudit) atau memakai kurs pajak untuk tagihan komersial.

Konsekuensi: layar biaya job menampilkan kedua kurs berdampingan dengan label jelas; snapshot kurs tersimpan di level PIB (kurs pajak) dan level invoice (kurs internal); v1 hanya menerbitkan tagihan IDR — invoice USD adalah pengecualian manual di luar sistem.
