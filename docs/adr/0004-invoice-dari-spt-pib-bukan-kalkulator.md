# 0004 — Invoice pungutan negara dari SPT PIB, bukan dari Kalkulator PIB

Konteks: Kalkulator PIB adalah estimator internal (keputusan Ronda 1), sedangkan tagihan ke importir harus memakai angka resmi. Keputusan: blok pungutan negara pada invoice (BM, PPN Impor, PPh 22) mengambil angka dari SPT PIB / billing code pada Snapshot PIB yang difile — bukan dari Kalkulator PIB. Pass-through harus bisa dibuktikan sistem: yang ditagih = aktual setelah konversi Kurs Internal; selisih hanya lewat penyesuaian yang disetujui dengan alasan tercatat. Alternatif yang ditolak: memakai hasil kalkulator untuk billing (selisih estimator-vs-filed tidak akan terdeteksi).

Konsekuensi: invoice menunggu data filing; Snapshot PIB menjadi sumber tunggal angka pungutan untuk billing; biaya pihak ketiga menyimpan DPP dan PPN dari faktur vendor sebagaimana adanya, bukan dihitung ulang.
