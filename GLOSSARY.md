# Freight Forwarding — Konteks Tunggal

Sistem pengurusan impor laut milik satu perusahaan PPJK. Konteks tunggal; seluruh istilah di bawah adalah bahasa kanonik untuk desain, database, dan UI.

## Language

**PPJK**:
Pengusaha Pengurusan Jasa Kepabeanan — perusahaan pemilik sistem ini; berizin dan bertanggung jawab hukum atas pemberitahuan pabean yang diajukan atas nama importir.
_Avoid_: forwarder (terlalu luas), agen (ambigu)

**Importir**:
Perusahaan klien pemilik barang yang menggunakan jasa PPJK; pihak yang ditagih atas biaya job.
_Avoid_: client, customer, consignee (consignee adalah peran pada B/L, belum tentu pihak yang ditagih)

**Job**:
Satu order pengurusan impor end-to-end dari satu importir — dari penerimaan dokumen asal sampai tagihan lunas dan kontainer kembali.
_Avoid_: shipment, order, booking

**PIB**:
Pemberitahuan Impor Barang — dokumen pabean impor; dalam sistem merujuk pada draf isian data sebelum diajukan.
_Avoid_: memakai "PIB" untuk dokumen yang sudah difile

**Kalkulator PIB**:
Fitur kalkulasi pungutan negara (BM, PPN, PPh 22, dst) untuk estimasi internal sebelum filing; bukan dokumen resmi.
_Avoid_: SPT PIB (istilah terpisah, lihat bawah)

**SPT PIB**:
Pemberitahuan Impor Barang yang telah difile ke CEISA dan memiliki nomor aju — dokumen resmi di luar sistem (v1).
_Avoid_: memakainya untuk draf internal

**Dokumen Asal**:
Dokumen komersial yang diterima dari importir sebagai basis entri job: Invoice, Packing List, B/L, dan Form FTA bila ada.

## Kontainer & Perjalanan

**Kontainer**:
Unit fisik dalam Job — punya nomor, ukuran, tipe, nomor seal, dan berat. Milestone fisik (gate-out, gate-in empty) dicatat per kontainer; biaya ditotalkan di level job.
_Avoid_: shipment (untuk kontainer), unit

**Milestone**:
Peristiwa bertanggal yang dicatat per job atau per kontainer, dengan waktu kejadian, penginput, dan bukti; sumber kebenaran status.
_Avoid_: status (milestone adalah fakta, status adalah turunannya)

**DO**:
Delivery Order — dokumen dari shipping line untuk pengambilan kontainer; dicatat di level job dan boleh terbit kapan saja setelah discharge.

**Demurrage**:
Biaya penumpukan kontainer di terminal setelah free time demurrage habis. Hari 0 = tanggal discharge; hari terakhir yang dihitung = tanggal gate-out full; dicatat per kontainer.

**Detention**:
Biaya keterlambatan pengembalian kontainer ke shipping line. Hari 0 = tanggal gate-out full; berakhir saat gate-in empty; dicatat per kontainer. Tanggal gate-out tidak dihitung ganda di kedua biaya.

**Free Time**:
Masa bebas biaya. Jumlah hari demurrage dan detention dikonfigurasi terpisah per line, masing-masing dengan start offset (H+0/H+1) dan tipe kalender (kalender/kerja) sendiri.

**Tarif Line**:
Master tarif ber-versi dengan tanggal berlaku; kuncinya line + jenis biaya + ukuran kontainer + tier hari. Perubahan tarif tidak berlaku retroaktif pada job lama.
_Avoid_: hardcode tarif di kode; angka tarif contoh sebagai default

**Surat Jalan**:
Dokumen pengiriman per trip per kontainer; memuat nomor job, nomor kontainer, nama driver, nopol, dan nomor HP driver.
_Avoid_: surat jalan per job (satu job bisa berkirim di hari berbeda)

## Kurs

**Kurs Internal**:
Kurs komersial perusahaan — satu resmi per hari per company per mata uang, ditetapkan Finance dengan jam cutoff; dipakai untuk konversi tagihan ke importir. Riwayatnya tidak dapat diedit setelah dipakai invoice.
_Avoid_: kurs pajak (istilah terpisah)

**Kurs Pajak**:
Kurs Kemenkeu (NDPBM) untuk kalkulasi PIB; diambil otomatis dan disimpan di level PIB.
_Avoid_: kurs komersial internal

## Status

**Status Progres**:
Tahap perjalanan job yang dihitung dari milestone — bukan kolom manual; di-cache untuk dashboard dan dihitung ulang setiap perubahan milestone.
_Avoid_: edit status manual; menjadikan masalah sebagai status

**Problem Flag**:
Penanda masalah (Hold, Dispute, Dokumen Bermasalah) yang terpisah dari status progres; job bisa berprogres sambil di-hold.
_Avoid_: status masalah (yang menyiratkan progres hilang)

## Cabang Alur

**Cabang Pabean**:
Rangkaian milestone administratif: PIB disiapkan → difile menjadi SPT PIB → SPPB. Validasi kronologis hanya berlaku di dalam cabang ini.

**Cabang Logistik**:
Rangkaian milestone fisik: discharge → DO → gate-out full → terkirim → gate-in empty. Validasi kronologis hanya berlaku di dalam cabang ini.
_Avoid_: satu urutan status linear yang menggabungkan kedua cabang

## Biaya & Dana

**Real-cost**:
Biaya aktual per komponen pada job (pungutan negara, biaya pihak ketiga, jasa) — berstatus Estimasi atau Aktual, dengan mata uang asal, snapshot kurs, dan bukti.

**Pass-through**:
Komponen biaya yang ditagih ke importir sama dengan aktual setelah konversi Kurs Internal, tanpa markup; selisih hanya boleh muncul lewat penyesuaian yang disetujui dan tercatat.
_Avoid_: markup di pass-through

**Disbursement**:
Pembayaran yang dilakukan PPJK atas nama importir (termasuk pungutan negara) dan ditagih kembali apa adanya di blok pungutan negara invoice.

**Jasa PPJK**:
Komponen pendapatan perusahaan — service fee flat per job (default) atau per kontainer; dipisahkan dari pass-through di invoice.

**Deposit Kontainer**:
Dana jaminan refundable yang dibayar ke line/depot, dicatat per kontainer dengan status Ditahan / Dikembalikan penuh / Dikembalikan sebagian / Hangus sebagian / Hangus penuh. Bukan biaya job; hanya porsi hangus diposting ke biaya, sebesar porsi itu, dengan bukti debit note.
_Avoid_: dicampur ledger biaya; dicatat per job

**Titipan Dana**:
Pembayaran importir yang diterima PPJK dan belum dialokasi ke job tertentu; pot dana yang berbeda dari Deposit Kontainer. Saldo dicatat per importir dengan asal pembayaran, tanggal, dan riwayat alokasi.
_Avoid_: deposit (istilah terpisah)

**Uang Muka**:
Dana yang ditransfer importir kepada PPJK sebelum tagihan final — dasarnya estimasi pungutan dari Kalkulator PIB + estimasi biaya + jasa, bukan persentase bebas. Dialokasi dan disettle per job; sisa dikembalikan dan tidak dibawa ke job berikutnya tanpa instruksi tertulis.
_Avoid_: Titipan Dana (pot dana lintas job); DP persentase sembarangan; dibiarkan sebagai fee tersembunyi

**POD**:
Proof of Delivery — bukti terkirim ke gudang importir (mis. Surat Jalan bertanda tangan), ter-link ke Surat Jalan terkait.
_Avoid_: file POD lepas tanpa kaitan

## Filing

**Snapshot PIB**:
Versi angka pungutan yang dikunci immutable saat approval/filing — berisi seluruh input dan hasil kalkulasi; nomor aju, billing code, dan NTPN menempel pada versi ini, bukan pada job secara umum.
_Avoid_: merevisi snapshot lama; menautkan nomor aju ke job

## Dasar Fiskal

**Nilai Pabean**:
CIF dalam rupiah — FOB + asuransi + freight dikonversi dengan Kurs Pajak per mata uang, periode Rabu–Selasa yang sama; dasar perhitungan Bea Masuk (PMK 144/PMK.04/2022).
_Avoid_: dicampur dengan Nilai Impor; dasar hukum PMK 203/2017 (itu barang penumpang)

**Nilai Impor**:
Nilai Pabean + Bea Masuk + pungutan lainnya — dasar pengenaan PPN, PPh 22, dan PPnBM impor.
_Avoid_: dipakai sebagai dasar Bea Masuk

**Seri Barang**:
Satu baris barang pada PIB dengan HS-nya sendiri — unit perhitungan tarif, flag barang mewah, flag pengecualian, dan pembulatan statutori; bukan level Job.
_Avoid_: flag atau pembulatan sekali di level Job

## Tata Kelola

**Maker-Checker**:
Aturan bahwa pencipta dan penyetuju suatu aksi harus dua orang berbeda — ditegakkan per user ID, tidak dapat dilewati walau satu orang memegang beberapa peran. Admin bukan superuser.
_Avoid_: self-approval; peran jamak sebagai alasan melewati approval

**Batas Approve**:
Limit nominal per user Finance untuk menyetujui invoice — berlaku atas total invoice DI LUAR Blok Pungutan Negara. Persetujuan kedua mengikat; persetujuan pertama tercatat sebagai review. "Direktur" adalah user dengan batas tanpa batas, tetap tidak boleh menyetujui invoice buatannya sendiri; perubahan batas oleh Admin dengan tanggal berlaku.
_Avoid_: batas per peran; batas yang mencakup pungutan negara

**Blok Invoice**:
Tiga blok tagihan: (a) pungutan negara dari Snapshot SPT PIB — disbursement tanpa PPN jasa, PPN Impor di sini bukan PPN Keluaran PPJK; (b) biaya pihak ketiga — DPP & PPN disimpan apa adanya dari faktur vendor (faktur tanpa PPN disimpan 0 dengan alasan); (c) jasa PPJK — PPN 12% atas DPP Nilai Lain 11/12 dari nilai jasa itu sendiri.
_Avoid_: menghitung ulang PPN vendor; hardcode tarif PPN jasa
