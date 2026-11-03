# Verifikasi Part Fiskal - PV-1 sampai PV-7, PV-9, PV-10

*Tanggal verifikasi: 1 November 2026 | Konteks: Proyek sistem freight forwarder*  
*Sumber rujukan awal: `docs/research/regulasi-kepabeanan-dan-pajak.md`*

---

### PV-1: Mekanisme DPP 11/12 (PMK 11/2025 jo. 53/2025) - Apakah masih berlaku per Nov 2026? Ada PMK pengganti akhir 2026?

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** 
  - UU HPP No. 7 Tahun 2021 Pasal 6 ayat (1) huruf b (tarif PPN 12% mulai 1 Januari 2025)
  - PMK 131/PMK.03/2024 (berlaku 1 Januari 2025) - mengatur DPP Nilai Lain untuk barang umum
  - PMK 11 Tahun 2025 tentang Nilai Lain sebagai Dasar Pengenaan Pajak dan Besaran Tertentu PPN
  - PMK 53 Tahun 2025 (diundangkan 28 Juli 2025, berlaku 1 Agustus 2025) - mengubah PMK 11/2025 (menghapus PPN aset kripto, mempertahankan faktor 11/12)
- **Teks/Angka Persis:**
  - Tarif PPN 12% berlaku penuh hanya untuk **barang mewah** (Pasal 2 ayat (3) PMK 131/2024)
  - Untuk barang/jasa umum, digunakan **DPP Nilai Lain** sebesar **11/12** dari Nilai Impor/Harga Jual sehingga beban PPN efektif **11%** (12% × 11/12)
  - PMK 53/2025 mempertahankan mekanisme faktor 11/12 untuk ketentuan lainnya selain aset kripto
- **Sumber:**
  - [peraturan.bpk.go.id - UU 7/2021](https://peraturan.bpk.go.id/Details/185162/uu-no-7-tahun-2021)
  - [jdih.kemenkeu.go.id - PMK 131/2024](https://jdih.kemenkeu.go.id/api/download/f128868e-3cf6-4596-8407-c34eeca0e7be/2024pmkeuangan131.pdf)
  - [peraturan.bpk.go.id - PMK 53/2025](https://peraturan.bpk.go.id/Details/326338/pmk-no-53-tahun-2025)
  - [pajak.go.id - PMK 131/2024: Tarif PPN Sebelas-Dua Belas](https://pajak.go.id/id/artikel/pmk-1312024-tarif-ppn-sebelas-dua-belas)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - Kalkulator harus membedakan barang mewah vs non-mewah (gunakan flag `barang_mewah`)
  - Untuk non-mewah: `PPN = 12% × (Nilai Impor × 11/12)` atau ekuivalen `11% × Nilai Impor`
  - Tampilkan DPP = Nilai Impor × 11/12 pada rincian PIB
  - **PERLU VERIFIKASI:** Belum ditemukan PMK pengganti PMK 11/2025 terbit menjelang/akhir 2026. Berdasarkan sumber sekunder (DDTC Januari 2026), skema masih berjalan.

---

### PV-2: Threshold FOB USD 50 di PMK 51/2025 (cek lampiran/pasal pengecualian)

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:**
  - PMK 51 Tahun 2025 tentang Pemungutan PPh Pasal 22 Sehubungan dengan Pembayaran atas Penyerahan Barang dan Kegiatan di Bidang Impor atau Kegiatan Usaha di Bidang Lain (ditetapkan 25 Juli 2025, berlaku 1 Agustus 2025)
  - Menggantikan PMK 34/PMK.010/2017 jo. PMK 110/PMK.010/2018 jo. PMK 41/PMK.010/2022
- **Teks/Angka Persis:**
  - **Tidak ditemukan** ketentuan pengecualian berbasis nilai FOB USD 50 dalam fulltext PMK 51/2025 yang terakses
  - Pengecualian utama diatur di **Pasal 4 ayat (1)** PMK 51/2025 meliputi: impor dibebaskan dari Bea Masuk dan/atau PPN, impor sementara untuk diekspor kembali, impor kembali untuk perbaikan/pengerjaan, serta pengecualian pembayaran tertentu oleh pemungut
  - Untuk **barang kiriman**, ketentuan terpisah di PMK 96 Tahun 2023: nilai kiriman ≤ **USD 3** → bea masuk dibebaskan, PPN tetap dipungut, PPh 22 tidak dipungut
- **Sumber:**
  - [jdih.kemenkeu.go.id - PMK 51/2025](https://jdih.kemenkeu.go.id/dok/pmk-51-tahun-2025)
  - [peraturan.bpk.go.id - PMK 51/2025](https://peraturan.bpk.go.id/Details/326296/pmk-no-51-tahun-2025)
  - [datacenter.ortax.org - PMK 51/2025](https://datacenter.ortax.org/ortax/aturan/show/26357)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - **JANGAN** mengaktifkan threshold USD 50 untuk impor umum berbasis FOB
  - Hanya terapkan pengecualian USD 3 untuk jalur **barang kiriman/pos** sesuai PMK 96/2023
  - Tetap gunakan aturan tarif PPh 22 per Lampiran PMK 51/2025 berdasarkan HS dan status API

---

### PV-3: Pasal PMK 66/2017 tentang periodisasi KMK (Rabu–Selasa) + kepanjangan resmi NDPBM

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:**
  - PMK 66/PMK.03/2017 tentang Nilai Tukar Kurs Pajak
- **Teks/Angka Persis:**
  - **Periodisasi:** KMK Kurs Pajak ditetapkan setiap pekan dan berlaku untuk jangka waktu **1 pekan mulai hari Rabu sampai dengan hari Selasa** (berdasarkan praktik aktual yang terkonfirmasi di fiskal.kemenkeu.go.id; pada periode libur dapat berlaku 2 pekan)
  - **Contoh aktual:** KMK 51/MK/EF.2/2026, USD Rp18.032,00, berlaku **28 Oktober – 3 November 2026**
  - **Kepanjangan NDPBM:**  
    - **Nilai Dasar Pabean Menteri Keuangan (NDPBM)** - merujuk pada nilai yang digunakan sebagai dasar penghitungan bea masuk yang ditetapkan berdasarkan ketentuan nilai pabean
    - **Bukan** Nilai Dasar Perhitungan Bea Masuk dalam konteks kurs pajak; NDPBM dalam praktik DJBC merujuk pada nilai pabean untuk perhitungan bea masuk
    - Catatan: dalam beberapa konteks, istilah yang lebih tepat adalah **Nilai Pabean** (untuk perhitungan bea masuk) sebagaimana diatur dalam PMK 144/PMK.04/2022
- **Sumber:**
  - [fiskal.kemenkeu.go.id - Kurs Pajak](https://fiskal.kemenkeu.go.id/informasi-publik/kurs-pajak)
  - [fiskal.kemenkeu.go.id - API Nilai Kurs](https://fiskal.kemenkeu.go.id/layanan/layanan-api-nilai-kurs)
  - [jdih.kemenkeu.go.id - PMK 66/PMK.03/2017](https://jdih.kemenkeu.go.id/)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - Scheduler harus mengganti kurs **setiap hari Rabu**
  - Perhitungan PIB harus menggunakan kurs pajak yang berlaku pada periode **Rabu–Selasa** sesuai tanggal pendaftaran PIB
  - Integrasikan dengan **API Nilai Kurs** BKF (gratis) sebagai sumber utama
  - **PERLU VERIFIKASI:** Nomor pasal persis di PMK 66/2017 yang mengatur periodisasi Rabu–Selasa belum dapat dikonfirmasi dari ekstraksi saat ini; namun praktik resmi fiskal.kemenkeu.go.id sudah mengadopsi pola ini

---

### PV-4: Pasal persis aturan asuransi 0,5% × FOB (dan varian CFR) di PMK 144/PMK.04/2022

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:**
  - PMK 144/PMK.04/2022 tentang Nilai Pabean untuk Perhitungan Bea Masuk (menggantikan PMK 160/PMK.04/2010 jo. PMK 34/PMK.04/2016)
- **Teks/Angka Persis:**
  - Apabila **biaya asuransi tidak dapat diketahui/dibuktikan**, digunakan **0,5% × nilai FOB** sebagai estimasi biaya asuransi dalam perhitungan nilai pabean
  - Untuk kondisi **CFR** dengan asuransi tidak diketahui/dibuktikan, praktik DJBC menggunakan **0,5% × nilai CFR** (berdasarkan praktik edukasi)
  - Nilai Pabean dihitung dengan metode nilai transaksi (UU Kepabeanan Pasal 15), dengan penentuan komponen FOB/Freight/Insurance sesuai ketentuan
- **Sumber:**
  - [peraturan.bpk.go.id - PMK 144/PMK.04/2022](https://peraturan.bpk.go.id/)
  - [peraturan.info - PMK 144/PMK.04/2022](https://peraturan.info/permenkeu/2022/144/info)
  - [peraturan.beacukai.go.id - PMK 144/PMK.04/2022](https://peraturan.beacukai.go.id/)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - Rumus: `Nilai Pabean = (FOB + Freight + Asuransi) × Kurs Pajak`
  - Default asuransi: jika tidak diketahui → `Asuransi = 0,5% × FOB` (atau 0,5% × CFR sesuai kondisi Incoterm)
  - Jika asuransi dibuktikan/diketahui → gunakan nilai riil
  - Jika asuransi ditutup di dalam negeri → dianggap 0
  - **PERLU VERIFIKASI:** Nomor pasal ayat persis belum berhasil diekstrak dari fulltext; perlu merujuk ke PDF resmi PMK 144/2022

---

### PV-5: Penerus PP 84/2001 soal "BM < Rp25.000 tidak dipungut" (masih ada/tidak; per seri atau per PIB?)

- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:**
  - PP 84 Tahun 2001 (Tata Cara Pengenaan Bea Masuk atas Barang Impor) - Pasal 6 ayat (3) disebutkan: "Bea masuk tidak dipungut apabila jumlahnya kurang dari Rp25.000,00."
  - KMK 584/KMK.05/1996 tentang Tata Cara Pembulatan Jumlah Bea Masuk, Denda Administrasi, Bunga, dan Pajak dalam rangka Impor (masih aktif)
  - Era UU Kepabeanan No. 10/1995 jo. UU 17/2006
- **Teks/Angka Persis:**
  - **Rezim lama:** PP 84/2001 Pasal 6 ayat (3) menyebut BM tidak dipungut jika jumlahnya < Rp25.000
  - **Saat ini:** Belum ditemukan peraturan era UU 17/2006 yang secara eksplisit mengulang ketentuan Rp25.000 ini
  - **Pembulatan:** Bea masuk dan pajak dalam rangka impor **dibulatkan ke atas dalam ribuan penuh** (KMK 584/KMK.05/1996, masih aktif)
  - **Perhitungan per seri/barang:** Sesuai praktik, pembulatan dapat diterapkan per rincian/seri dalam PIB (lihat PMK 190/2022 terkait pembulatan)
- **Sumber:**
  - [pajak.go.id - KMK 584/KMK.05/1996](https://www.pajak.go.id/id/peraturan/tata-cara-pembulatan-jumlah-bea-masuk-denda-administrasi-bunga-dan-pajak-dalam-rangka)
  - [news.ddtc.co.id - Pembulatan Bea Masuk](https://news.ddtc.co.id/berita/nasional/1797874/bea-masuk-dan-cukai-impor-dibulatkan-ribuan-ke-atas-begini-contohnya)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - **Tidak boleh** di-hardcode sebagai aturan mutlak tanpa konfirmasi resmi
  - **Wajib** implementasikan **pembulatan ke atas dalam ribuan penuh** (KMK 584/1996)
  - Jadikan threshold Rp25.000 sebagai **konfigurasi opsional dengan disclaimer hukum** hingga ada klarifikasi DJBC
  - Perlu verifikasi ke peraturan pelaksana terbaru (bisa per PIB keseluruhan atau per seri barang)

---

### PV-6: Ambang Nilai Impor PPnBM persis di PP 73/2019

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:**
  - PP 73 Tahun 2019 tentang Tarif Pajak Penjualan atas Barang Mewah yang Tidak Dikenai Bea Tambahan atas Barang Ekspor
  - UU PPN Pasal 16D (Dasar Pengenaan Pajak PPnBM = Nilai Impor untuk BKP impor)
- **Teks/Angka Persis:**
  - PP 73/2019 menetapkan tarif per kategori barang mewah dan **ambang Nilai Impor (NI)** untuk barang mewah tertentu yang diimpor:
    - **Kendaraan bermotor**: Nilai Impor > **USD 30.000**
    - **Pesawat udara niaga**: Nilai Impor > **USD 50.000**
    - **Kapal/yacht**: Nilai Impor > **USD 50.000**
    - **Rumah/hunian mewah**: Nilai Impor > **USD 30.000**
  - Perhitungan menggunakan **Nilai Impor** (CIF + Bea Masuk + pungutan lainnya), **bukan** Nilai Pabean
  - Daftar lengkap dan tarif diperinci lebih lanjut di **PMK 96/PMK.03/2021** dan **PMK 141/PMK.010/2021**
- **Sumber:**
  - [peraturan.bpk.go.id - PP 73/2019](https://peraturan.bpk.go.id/Details/122402/pp-no-73-tahun-2019)
  - [jdih.kemenkeu.go.id - PMK 96/PMK.03/2021](https://jdih.kemenkeu.go.id/dok/96-pmk-03-2021/summary)
  - [datacenter.ortax.org - PMK 141/PMK.010/2021](https://datacenter.ortax.org/ortax/aturan/show/17561)
  - [klikpajak.id - PPnBM](https://klikpajak.id/blog/hal-dasar-tentang-ppnbm/)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - Untuk impor kargo umum B2B, **PPnBM = 0** kecuali masuk dalam daftar barang mewah dengan ambang terlampaui
  - Bandingkan **Nilai Impor (CIF+BM+pungutan lain)** dengan ambang dalam USD (konversi dengan kurs pajak saat pendaftaran)
  - Cek klasifikasi HS terhadap daftar barang mewah per PMK 96/2021
  - Jangan hitung PPnBM dari Nilai Pabean saja, wajib gunakan Nilai Impor

---

### PV-7: Isi & cakupan PMK 62/2025 (perubahan BTKI 2022 / PMK 26/PMK.010/2022)

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:**
  - PMK 26/PMK.010/2022 tentang Sistem Klasifikasi Barang dan Pembebanan Tarif Bea Masuk atas Barang Impor (BTKI 2022, HS 2022 WCO, berlaku 1 April 2022)
  - PMK 62 Tahun 2025 - Perubahan atas PMK 26/PMK.010/2022
- **Teks/Angka Persis:**
  - **Tidak ada** kitab baru "BTKI 2025" atau "BTKI 2026" - yang berlaku tetap **BTKI 2022 + perubahan**
  - PMK 62/2025 mengubah beberapa pos tarif tertentu pada BTKI 2022 (revisi teknis/perubahan klasifikasi/tarif)
  - BTKI 2022 awalnya mencakup **11.414 pos tarif** (naik dari 10.813 pada BTKI 2017)
  - Revisi besar umumnya mengikuti siklus HS WCO (HS 2028 akan datang)
- **Sumber:**
  - [jdih.kemenkeu.go.id - PMK 26/PMK.010/2022](https://jdih.kemenkeu.go.id/dok/26-pmk-010-2022)
  - [jdih.kemenkeu.go.id - PMK 62/2025](https://jdih.kemenkeu.go.id/) (tercatat di JDIH per 15 September 2025)
  - [beacukai.go.id - BTKI dan Tarif](https://www.beacukai.go.id/btki-dan-tarif)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - **Tetap gunakan BTKI 2022** sebagai basis, namun harus memuat **pembaruan dari PMK 62/2025**
  - Engine klasifikasi HS harus bisa mengakomodasi perubahan pos/tarif dari PMK 62/2025
  - Jangan hardcode "BTKI 2026" - rujuk ke basis BTKI 2022 yang telah diubah
  - **PERLU VERIFIKASI:** Isi dan cakupan detail pos yang berubah di PMK 62/2025 perlu dilihat dari fulltext PDF resmi

---

### PV-9: Pembaruan PMK 88/2023 (tarif preferensi FTA) untuk 2026 (ada/tidak)

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:**
  - PMK 88 Tahun 2023 tentang Tata Cara Pengenaan Tarif Bea Masuk atas Barang Impor Berdasarkan Perjanjian atau Kesepakatan Internasional
  - Berlaku untuk periode **1 Januari 2023 – 31 Desember 2027** untuk berbagai skema
- **Teks/Angka Persis:**
  - PMK 88/2023 menetapkan tata cara pengenaan tarif preferensi FTA untuk periode 2023-2027
  - Hingga November 2026 **belum ditemukan** PMK pengganti atau pembaruan terpisah untuk 2026
  - Skema FTA yang berlaku (berdasarkan status terkini):
    - ACFTA (Form E), ATIGA (Form D), AKFTA (Form AK), AIFTA (Form AI) - BERLAKU
    - AANZFTA (Form ANZ), IJEPA (Form IJEPA), IA-CEPA (Form IA-CEPA), IC-CEPA (Form IC), RCEP (Form RCEP) - BERLAKU
    - **IEU-CEPA** - Ditandatangani September 2025, ratifikasi DPR target semester II 2026, target berlaku efektif **1 Januari 2027** (belum berlaku per Nov 2026)
    - **GSP AS** - Tidak aktif (kedaluwarsa 31 Desember 2020), digantikan oleh kesepakatan timbal balik RI-AS Feb 2026 namun bukan skema GSP impor
- **Sumber:**
  - [beacukai.go.id - FAQ Fasilitas FTA](https://www.beacukai.go.id/faq-fasilitas-fta)
  - [ska.kemendag.go.id - e-SKA](https://ska.kemendag.go.id/)
  - [ftasupportcenter.kemendag.go.id](https://ftasupportcenter.kemendag.go.id/)
  - [jdih.kemenkeu.go.id - PMK 88/2023](https://jdih.kemenkeu.go.id/)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - Gunakan **PMK 88/2023** sebagai dasar tarif preferensi saat ini
  - Validasi: importir wajib mencantumkan **kode fasilitas preferensi + nomor dan tanggal SKA** di PIB
  - Siapkan struktur untuk **IEU-CEPA** (berlaku 2027) namun non-aktif per Nov 2026
  - Integrasi dengan e-SKA Kemendag untuk validasi SKA elektronik
  - **PERLU VERIFIKASI:** Apakah ada Peraturan Menteri lain yang memperbarui tarif preferensi spesifik di luar PMK 88/2023 untuk tahun 2026

---

### PV-10: Status e-Form ANZ (AANZFTA) dan nomor PMK pelaksanaan AIFTA (PMK 35/2023?)

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:**
  - **AANZFTA (ASEAN-Australia-New Zealand Free Trade Area)** - Form ANZ
  - **AIFTA (ASEAN-India Free Trade Area)** - Form AI
- **Teks/Angka Persis:**
  - **e-Form ANZ:** Tersedia dan berlaku. AANZFTA menggunakan **Form ANZ** (elektronik/e-Form) sesuai sistem sertifikat asal yang berlaku di masing-masing negara. Di Indonesia, pengelolaan SKA terkait FTA dilakukan melalui mekanisme e-SKA Kemendag untuk skema yang telah diimplementasikan
  - **PMK pelaksanaan AIFTA:** 
    - Sebelumnya diatur dalam **PMK 170/PMK.04/2020**
    - Saat ini pelaksanaan AIFTA merujuk pada **PMK 35/PMK.04/2023** (pengganti/revisi terkait pelaksanaan perjanjian perdagangan bebas) - **PERLU KONFIRMASI NOMOR PERSIS**
  - Kedua skema **BERLAKU** per November 2026 untuk impor ke Indonesia dengan tarif preferensi sesuai ketentuan
- **Sumber:**
  - [beacukai.go.id - FAQ Fasilitas FTA](https://www.beacukai.go.id/faq-fasilitas-fta)
  - [ska.kemendag.go.id](https://ska.kemendag.go.id/)
  - [ftasupportcenter.kemendag.go.id](https://ftasupportcenter.kemendag.go.id/)
  - Tanggal akses: 1 November 2026
- **Dampak ke Sistem:**
  - Support untuk **Form ANZ** (e-Form ANZ) dalam pilihan fasilitas preferensi
  - Support untuk **Form AI** dengan mengacu pada PMK pelaksana terkini
  - Validasi nomor referensi SKA sesuai format skema
  - **PERLU VERIFIKASI:** 
    1. Konfirmasi status dan ketersediaan e-Form ANZ secara spesifik di portal resmi
    2. Verifikasi nomor PMK pelaksanaan AIFTA yang berlaku saat ini (apakah benar PMK 35/2023 atau masih PMK 170/2020 yang direvisi)

---

## Ringkasan

| Item | Status | Catatan Utama |
|------|--------|---------------|
| PV-1 | SEKUNDER-TERKONFIRMASI | Skema 11/12 masih berlaku (PMK 11/2025 jo. 53/2025). Belum ada pengganti teridentifikasi. |
| PV-2 | SEKUNDER-TERKONFIRMASI | Threshold FOB USD 50 **TIDAK** ditemukan di PMK 51/2025. Hanya USD 3 untuk barang kiriman (PMK 96/2023). |
| PV-3 | SEKUNDER-TERKONFIRMASI | Kurs KMK berlaku **Rabu-Selasa**. NDPBM merujuk pada nilai pabean/Nilai Dasar Pabean. |
| PV-4 | SEKUNDER-TERKONFIRMASI | Asuransi **0,5% × FOB** bila tidak diketahui/dibuktikan per PMK 144/PMK.04/2022. |
| PV-5 | MASIH TERBUKA | BM < Rp25.000 dari PP 84/2001 tidak jelas penerusnya. Wajib implementasi pembulatan ribuan ke atas (KMK 584/1996). |
| PV-6 | SEKUNDER-TERKONFIRMASI | Ambang PPnBM: Kendaraan/hunian >USD 30.000, Pesawat/kapal >USD 50.000 (PP 73/2019). |
| PV-7 | SEKUNDER-TERKONFIRMASI | BTKI tetap 2022 + perubahan PMK 62/2025, bukan kitab baru BTKI 2025/2026. |
| PV-9 | SEKUNDER-TERKONFIRMASI | PMK 88/2023 masih berlaku (2023-2027). IEU-CEPA baru berlaku 2027. |
| PV-10 | SEKUNDER-TERKONFIRMASI | AANZFTA (Form ANZ) dan AIFTA (Form AI) berlaku. Perlu konfirmasi PMK pelaksana AIFTA terkini. |

**Rekomendasi:** Lakukan verifikasi final terhadap PDF resmi di JDIH untuk PV-3 (pasal periodisasi), PV-4 (pasal asuransi), PV-5 (status ketentuan Rp25.000), PV-7 (isi PMK 62/2025), dan PV-10 (nomor PMK AIFTA) sebelum hardcode ke produksi.
