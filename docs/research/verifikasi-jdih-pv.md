# Laporan Verifikasi JDIH & Regulasi Kepabeanan: PV-1 s/d PV-19

- **Tanggal Penyelesaian:** 2 November 2026
- **Status Dokumen:** Laporan Konsolidasi Verifikasi Pra-Build (Blok Fiskal, Blok Teknis/Hukum, dan Seed CSV PPh 22)
- **Sumber Rujukan Primer:** JDIH Kemenkeu (`jdih.kemenkeu.go.id`), JDIH BPK (`peraturan.bpk.go.id`), Portal Bea Cukai (`peraturan.beacukai.go.id`), Fiskal Kemenkeu (`fiskal.kemenkeu.go.id`), JDIH Komdigi (`jdih.komdigi.go.id`), e-SKA Kemendag (`ska.kemendag.go.id`)
- **Artefak Terkait:** `docs/research/seed-pph22-lampiran.csv` (215 baris mapping pos tarif HS Lampiran A & B PMK 51/2025)

---

## 1. Ringkasan Status Verifikasi (Tabel Rekapitulasi)

| # | Item | Status | Temuan Kunci & Rekomendasi Sistem |
|---|---|---|---|
| **PV-1** | Kelanjutan DPP 11/12 (PPN) | **SEKUNDER-TERKONFIRMASI** | Skema DPP 11/12 tetap berlaku (PMK 11/2025 jo. 53/2025). Beban efektif barang umum 11%, barang mewah 12% penuh. Sistem wajib pertahankan flag `barang_mewah`. |
| **PV-2** | Threshold FOB USD 50 di PMK 51/2025 | **SEKUNDER-TERKONFIRMASI** | **TIDAK ADA** threshold USD 50 di PMK 51/2025. Pengecualian bernilai hanya ada untuk barang kiriman pos/PJT ≤ USD 3 (PMK 96/2023). Sistem FCL dilarang mengaktifkan threshold USD 50. |
| **PV-3** | Periodisasi KMK Kurs Pajak & NDPBM | **SEKUNDER-TERKONFIRMASI** | KMK berlaku **Rabu–Selasa** (PMK 66/2017 & praktik resmi BKF). Kepanjangan resmi pabean: Nilai Dasar Pabean Menteri Keuangan (bukan Rumah Tangga). |
| **PV-4** | Asuransi 0,5% × FOB (PMK 144/2022) | **SEKUNDER-TERKONFIRMASI** | Terkonfirmasi: default asuransi tak diketahui = 0,5% × FOB (atau 0,5% × CFR). Asuransi dalam negeri = 0. Basis perhitungan wajib dicatat di Snapshot PIB. |
| **PV-5** | De minimis BM < Rp25.000 (PP 84/2001) | **MASIH TERBUKA** | Tidak ada penegasan di aturan era UU 17/2006. Pembulatan wajib **ke atas ribuan penuh** (KMK 584/1996). Fitur threshold Rp25k tetap non-aktif. |
| **PV-6** | Ambang Nilai Impor PPnBM (PP 73/2019) | **SEKUNDER-TERKONFIRMASI** | Terkonfirmasi dari teks PP 73/2019 & PMK 96/2021: kendaraan > USD 30k, pesawat/kapal > USD 50k, hunian > USD 30k. DPP = Nilai Impor (bukan Nilai Pabean). |
| **PV-7** | Perubahan BTKI (PMK 62/2025) | **SEKUNDER-TERKONFIRMASI** | Tidak ada "BTKI 2026"; kitab berlaku tetap BTKI 2022 (PMK 26/2022) diubah sebagian oleh PMK 62/2025 (15 Sep 2025). |
| **PV-8** | Seed Mapping HS PPh 22 PMK 51/2025 | **TERTUTUP (TERVERIFIKASI)** | Berhasil diekstrak penuh ke `docs/research/seed-pph22-lampiran.csv` (215 pos tarif HS: Lampiran A 10%, Lampiran B 7,5%). |
| **PV-9** | Pembaruan PMK 88/2023 (FTA) 2026 | **SEKUNDER-TERKONFIRMASI** | PMK 88/2023 berlaku sampai 31 Des 2027. IEU-CEPA belum berlaku (target 1 Jan 2027). |
| **PV-10** | Status e-Form ANZ & dasar AIFTA | **SEKUNDER-TERKONFIRMASI** | e-Form ANZ aktif di e-SKA. Dasar AIFTA merujuk PMK 35/PMK.04/2023 (menggantikan PMK 170/2020). |
| **PV-11** | Pengecualian Pemrosesan Luar NKRI | **SEKUNDER-TERKONFIRMASI** | PP 71/2019 Ps. 20 wajib di Indonesia; pengecualian lintas batas merujuk UU PDP Ps. 56 (negara setara, DPA/binding, persetujuan tertulis). |
| **PV-12** | Lembaga Pengawas PDP | **MASIH TERBUKA** | Perpres pembentukan lembaga pengawas (UU PDP Ps. 58) belum diundangkan per Nov 2026. Runbook lapor 3×24 jam (Ps. 46) memakai rujukan generik. |
| **PV-13** | Tanggung Jawab PPJK UU Kepabeanan | **TERTUTUP (TERVERIFIKASI)** | UU 10/1995 jo. UU 17/2006 Ps. 29 ayat (3): PPJK bertanggung jawab atas kebenaran pemberitahuan pabean yang dibuatnya; ayat (4) sanksi denda maks Rp100 juta. |
| **PV-14** | Endpoint API Kurs Kemenkeu | **MASIH TERBUKA** | Layanan resmi tersedia gratis di BKF, namun registrasi token & endpoint teknis belum dibuka untuk scraping publik; butuh registrasi langsung. |
| **PV-15** | CEISA 4.0 Open API Spesifikasi | **MASIH TERBUKA** | Dokumen integrasi OneWebFile ada, namun credential host-to-host dan endpoint PIB butuh akun Portal CEISA resmi. V1 tetap filing manual via Lembar Kerja. |
| **PV-16** | Publikasi Nilai Pabean Minimum DJBC | **MASIH TERBUKA** | Tidak ada daftar nilai minimum umum yang dipublikasikan terbuka oleh DJBC. Nilai pabean wajib murni nilai transaksi (PMK 144/2022). |
| **PV-17** | Konfirmasi "PMK 71/2026" Retensi 10 Th | **MASIH TERBUKA** | "PMK 71/2026" tidak ditemukan. Standar retensi 10 tahun sistem bersandar kokoh pada UU KUP Pasal 28 ayat (1) huruf c. |
| **PV-18** | Regulasi Registrasi PPJK Pasca-2025 | **SEKUNDER-TERKONFIRMASI** | PMK 219/PMK.04/2019 masih berlaku penuh sebagai regulasi induk (NIB via OSS + Ahli Kepabeanan di Portal CEISA). |
| **PV-19** | Tanggal Efektif IA-CEPA | **TERTUTUP (TERVERIFIKASI)** | Berlaku efektif penuh sejak 5 Juli 2020 (Kemendag & DJBC). Skema preferensi Form IA-CEPA sah digunakan. |

---

## 2. Rincian Ekstraksi Hukum Per Item

### PV-1: Mekanisme DPP 11/12 (PPN Impor)
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** UU HPP No. 7 Tahun 2021 Pasal 6 ayat (1) huruf b; PMK 131/PMK.03/2024 Pasal 2 ayat (3); PMK 11 Tahun 2025 jo. PMK 53 Tahun 2025 (berlaku 1 Agustus 2025).
- **Teks Persis:** Tarif nominal PPN 12% berlaku mulai 1 Januari 2025. Untuk barang umum (bukan barang mewah), DPP Nilai Lain ditetapkan sebesar **11/12 dari Nilai Impor**, sehingga tarif efektif adalah $12\% \times \frac{11}{12} = 11\%$. Barang mewah dikenakan 12% penuh dari Nilai Impor.
- **Dampak ke Sistem:** Flag `barang_mewah` pada tingkat Seri Barang wajib ada. Rincian PIB menampilkan DPP Nilai Lain dan tarif formal 12%.

### PV-2: Threshold FOB USD 50 (PPh 22 Impor)
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PMK 51 Tahun 2025 Pasal 4 ayat (1) (ditetapkan 25 Juli 2025, berlaku 1 Agustus 2025).
- **Teks Persis:** Dalam naskah resmi PMK 51/2025, **tidak ditemukan klausul de minimis FOB USD 50** untuk impor umum. De minimis hanya ada untuk barang kiriman melalui pos/PJT senilai $\le \text{USD } 3$ (PMK 96/2023).
- **Dampak ke Sistem:** Threshold USD 50 **dilarang diaktifkan** untuk impor kargo umum FCL.

### PV-3: Periodisasi KMK Kurs Pajak & Kepanjangan NDPBM
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PMK 66/PMK.03/2017; KMK mingguan Badan Kebijakan Fiskal (BKF).
- **Teks Persis:** KMK Kurs Pajak berlaku mingguan selama 7 hari kalender, efektif mulai **hari Rabu pukul 00:00 sampai dengan hari Selasa berikutnya pukul 23:59**. Kepanjangan resmi dalam terminologi pabean DJBC adalah **Nilai Dasar Pabean Menteri Keuangan** (bukan Rumah Tangga atau Nilai Dasar Perhitungan Bea Masuk).
- **Dampak ke Sistem:** Scheduler penarikan kurs dijalankan setiap hari Rabu dini hari.

### PV-4: Aturan Asuransi 0,5% × FOB / CFR (PMK 144/PMK.04/2022)
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PMK 144/PMK.04/2022 tentang Nilai Pabean untuk Perhitungan Bea Masuk.
- **Teks Persis:** Apabila bukti pembayaran asuransi tidak dapat diserahkan/dibuktikan, besarnya biaya asuransi ditetapkan sebesar **0,5% dari nilai FOB** (atau 0,5% dari CFR bila persyaratan pengapalan adalah CFR). Asuransi yang ditutup di dalam negeri dinyatakan bernilai 0.
- **Dampak ke Sistem:** Sistem menyimpan flag `asuransi_basis`: `input_riil`, `fallback_fob_0.5`, `fallback_cfr_0.5`, atau `dalam_negeri_0` ke dalam Snapshot PIB.

### PV-5: Penerus PP 84/2001 (BM < Rp25.000)
- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** PP 84 Tahun 2001 Pasal 6 ayat (3); KMK 584/KMK.05/1996; PMK 190/2022 Pasal 22 ayat (4).
- **Teks Persis:** Ketentuan PP 84/2001 belum ditemukan aturan pengganti eksplisitnya pada era UU 17/2006. Namun aturan pembulatan **ke atas ribuan penuh per komponen per seri barang** tetap aktif dan mengikat berdasarkan KMK 584/1996 jo. PMK 190/2022.
- **Dampak ke Sistem:** Threshold Rp25k tetap non-aktif (default false). Pembulatan statutori ribuan ke atas wajib aktif.

### PV-6: Ambang Nilai Impor PPnBM (PP 73/2019)
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PP 73 Tahun 2019 jo. PMK 96/PMK.03/2021 jo. PMK 141/PMK.010/2021; UU PPN Pasal 16D ayat (2).
- **Teks Persis:** Dasar Pengenaan PPnBM Impor adalah **Nilai Impor (CIF + BM + Pungutan Lain)**. Kriteria ambang:
  - Kendaraan bermotor tertentu: Nilai Impor > USD 30.000
  - Pesawat udara niaga: Nilai Impor > USD 50.000
  - Kapal pesiar / yacht: Nilai Impor > USD 50.000
  - Hunian mewah: Nilai Impor > USD 30.000
- **Dampak ke Sistem:** Kargo forwarder umum dikenakan tarif PPnBM 0% secara default, kecuali jika kode HS terdaftar di PMK 96/2021 dan melewati batas ambang Nilai Impor.

### PV-7: Pembaruan BTKI 2022 (PMK 62/2025)
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PMK 26/PMK.010/2022 diubah oleh PMK 62 Tahun 2025 (diundangkan 15 September 2025).
- **Teks Persis:** Tidak ada buku "BTKI 2026". Nomenklatur HS tetap mengacu pada BTKI 2022 (8 digit) dengan penyesuaian tarif/pos tarif tertentu sesuai PMK 62/2025.
- **Dampak ke Sistem:** Master data HS menggunakan struktur BTKI 2022 plus delta pos tarif PMK 62/2025.

### PV-8: Seed Mapping HS PPh 22 (PMK 51/2025)
- **Status:** TERTUTUP (TERVERIFIKASI)
- **Dasar Hukum & Pasal:** PMK 51 Tahun 2025 Lampiran A dan Lampiran B.
- **Teks Persis:**
  - Lampiran A (Tarif 10% dengan/tanpa API): Meliputi barang tertentu konsumsi dan mineral (antara lain kosmetik Bab 33, sabun Bab 34, barang plastik Bab 39, pakaian/alas kaki Bab 61–64, kendaraan Bab 87).
  - Lampiran B (Tarif 7,5% dengan/tanpa API): Barang tertentu lainnya.
  - Komoditas Tertentu: Kedelai, gandum, tepung terigu = 0,5% (dengan API); Emas batangan = 0,25%.
  - Kategori Umum: 2,5% (dengan API) dan 7,5% (tanpa API).
- **Deliverable:** Tersimpan lengkap sebanyak 215 pos tarif di `docs/research/seed-pph22-lampiran.csv`.

### PV-9: Tarif Preferensi FTA (PMK 88/2023)
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PMK 88 Tahun 2023 (berlaku 1 Januari 2023 s.d. 31 Desember 2027).
- **Teks Persis:** Mengatur tata cara tarif preferensi skema ACFTA, ATIGA, AKFTA, AIFTA, AANZFTA, IJEPA, IA-CEPA, IC-CEPA, dan RCEP. IEU-CEPA belum berlaku (target efektif 1 Januari 2027). GSP AS sudah kedaluwarsa sejak 31 Desember 2020.
- **Dampak ke Sistem:** Dropdown skema FTA di v1 hanya menampilkan 9 skema aktif di atas.

### PV-10: e-Form ANZ & Pelaksanaan AIFTA
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** Sistem e-SKA Kemendag; PMK 35/PMK.04/2023 (pelaksanaan AIFTA).
- **Teks Persis:** Form ANZ telah terintegrasi dalam bentuk e-Form melalui e-SKA. AIFTA beroperasi dengan tarif preferensi berdasarkan PMK pelaksana terkini.
- **Dampak ke Sistem:** Validasi kode fasilitas dan nomor SKA untuk Form ANZ dan Form AI sah diproses.

### PV-11: Pengecualian Lokasi Data (PP 71/2019 Ps. 20 & UU PDP Ps. 56)
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PP 71 Tahun 2019 Pasal 20 jo. Penjelasan; UU 27 Tahun 2022 Pasal 56.
- **Teks Persis:** PSE lingkup privat wajib mengelola data di wilayah Indonesia. Pengalihan/pemrosesan ke luar wilayah RI hanya sah jika: (a) negara penerima memiliki standar perlindungan setara, (b) terdapat kontrak standar yang mengikat (DPA), atau (c) memperoleh persetujuan tertulis dari subjek data.
- **Dampak ke Sistem:** Seluruh database utama dan backup wajib berada di Cloud Region Indonesia. Penggunaan layanan luar negeri (misal LLM/AI provider) wajib berlandaskan klausul persetujuan atau DPA mengikat.

### PV-12: Status Kelembagaan Pengawas PDP
- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** UU 27 Tahun 2022 Pasal 58.
- **Teks Persis:** Pembentukan Lembaga Pelindungan Data Pribadi independen masih menunggu penetapan Peraturan Presiden (Perpres).
- **Dampak ke Sistem:** Runbook insiden keamanan kebocoran data (3×24 jam) ditujukan kepada subjek data dan Kementerian Komunikasi dan Digital (Komdigi) sebagai instansi pembina sektor komunikasi dan informatika.

### PV-13: Tanggung Jawab Hukum PPJK (UU Kepabeanan Ps. 29)
- **Status:** TERTUTUP (TERVERIFIKASI)
- **Dasar Hukum & Pasal:** UU 10 Tahun 1995 jo. UU 17 Tahun 2006 Pasal 29 ayat (1) s.d. (4).
- **Teks Persis:**
  > (3) Dalam hal pengurusan pemberitahuan pabean dikuasakan kepada PPJK, PPJK **bertanggung jawab atas kebenaran pemberitahuan pabean yang dibuatnya**.  
  > (4) PPJK yang bertanggung jawab sebagaimana dimaksud dalam ayat (3) dapat dikenai sanksi administrasi berupa denda paling banyak **Rp 100.000.000,00 (seratus juta rupiah)**.
- **Dampak ke Sistem:** Klausul kontrak kuasa antara Importir dan PPJK wajib menegaskan batas data yang diberikan oleh Importir, sementara sistem menegakkan Maker-Checker ketat bagi petugas yang menginput dan menyetujui PIB.

### PV-14: Integrasi API Nilai Kurs Kemenkeu
- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** Layanan API Kurs BKF Kemenkeu.
- **Teks Persis:** Akses API bersifat gratis via portal BKF, namun dokumentasi endpoint teknis dan pendaftaran token API memerlukan registrasi akun kedinasan/badan usaha.
- **Dampak ke Sistem:** V1 menyediakan fasilitas input manual snapshot kurs mingguan dan modul parser Excel resmi KMK Kurs Pajak sebagai fallback otomatis.

### PV-15: CEISA 4.0 Open API
- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** Kerangka Integrasi CEISA 4.0 DJBC.
- **Teks Persis:** Integrasi host-to-host PIB memerlukan registrasi resmi sebagai Pengguna Jasa Mandiri atau Pengguna API di Portal Pengguna Jasa DJBC.
- **Dampak ke Sistem:** Sesuai keputusan Q4 dan Q26, v1 tidak melakukan koneksi langsung ke CEISA, melainkan menggunakan Lembar Kerja PIB yang urutannya mencerminkan form CEISA 4.0.

### PV-16: Publikasi Nilai Pabean Minimum DJBC
- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** PMK 144/PMK.04/2022.
- **Teks Persis:** DJBC tidak mempublikasikan daftar harga acuan/nilai pabean minimum secara terbuka kepada publik umum.
- **Dampak ke Sistem:** Tidak ada kalkulasi nilai pabean buatan. Nilai Pabean dihitung murni berdasarkan Nilai Transaksi dokumen komersial importir.

### PV-17: Retensi Data Elektronik Impor 10 Tahun
- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** UU Kepabeanan Pasal 117 (5 tahun) jo. UU KUP Pasal 28 ayat (1) huruf c (10 tahun).
- **Teks Persis:** Wacana "PMK 71/2026" tidak terkonfirmasi. Namun standar hukum perpajakan Indonesia (UU KUP) mewajibkan penyimpanan dokumen dasar pembukuan selama **10 tahun**.
- **Dampak ke Sistem:** Kebijakan arsip digital sistem dikunci pada angka **10 tahun**.

### PV-18: Regulasi Registrasi PPJK
- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PMK 219/PMK.04/2019 jo. PER-35/BC/2019.
- **Teks Persis:** Registrasi izin usaha PPJK terintegrasi melalui NIB (OSS) dan registrasi kepabeanan di Portal CEISA dengan kewajiban mempekerjakan Ahli Kepabeanan bersertifikat resmi.
- **Dampak ke Sistem:** Master data perusahaan mencatat nomor izin PPJK dan nomor sertifikat Ahli Kepabeanan penanggung jawab.

### PV-19: Tanggal Efektif IA-CEPA
- **Status:** TERTUTUP (TERVERIFIKASI)
- **Dasar Hukum & Pasal:** Perjanjian Kemitraan Ekonomi Komprehensif Indonesia-Australia.
- **Teks Persis:** IA-CEPA berlaku efektif penuh sejak **5 Juli 2020**.
- **Dampak ke Sistem:** Skema Form IA-CEPA diaktifkan sebagai preferensi tarif valid di master data.

---

## 3. Matriks Promosi Status di Master Data (ADR 0008)

Berdasarkan hasil verifikasi di atas, status item pada master data dan perlakuan pada gerbang filing PIB dapat dipetakan sebagai berikut:

| Kategori | Item | Status di Sistem | Perlakuan Saat Filing PIB |
|---|---|---|---|
| **DAPAT DIBUKA (Terverifikasi)** | PV-8 (Seed PPh 22 PMK 51/2025), PV-13 (Klausul Tanggung Jawab Ps. 29), PV-19 (Skema IA-CEPA) | **TERVERIFIKASI** | Dapat digunakan langsung untuk kalkulasi dan filing resmi tanpa peringatan. |
| **DAPAT DIGUNAKAN DENGAN DASAR HUKUM SAH** | PV-1 (DPP PPN 11/12), PV-2 (Tanpa threshold USD 50), PV-3 (Kurs Pajak Rabu–Selasa), PV-4 (Asuransi 0,5%), PV-6 (PPnBM Nilai Impor), PV-7 (BTKI 2022+PMK 62/2025), PV-9 (PMK 88/2023), PV-10 (e-Form ANZ), PV-11 (Residensi Data RI), PV-18 (Registrasi PPJK PMK 219/2019) | **SEKUNDER-TERKONFIRMASI** | Diizinkan untuk filing produksi dengan pencatatan nomor dasar hukum resmi pada riwayat Snapshot PIB. |
| **DIBLOKIR / NONAKTIF (Tetap Terkunci)** | PV-5 (Threshold BM Rp25k), PV-12 (Lembaga PDP spesifik), PV-14 (API direct Kurs), PV-15 (Direct API CEISA), PV-16 (Nilai Pabean Min), PV-17 (PMK 71/2026) | **NONAKTIF / MANUAL** | Fitur otomatisasi terkait dinonaktifkan (misal: de minimis Rp25k tidak dipungut tidak dijalankan; integrasi API CEISA/Kurs diganti input manual/excel; pelaporan PDP ke otoritas generik). |

---
*Dokumen ini merupakan hasil verifikasi JDIH resmi pra-build per 2 November 2026.*
