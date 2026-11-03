# Verifikasi Part Teknis PV-11 s/d PV-19

*Tanggal verifikasi: 1 November 2026*  
*Sumber acuan: jdih.kemenkeu.go.id, peraturan.bpk.go.id, peraturan.beacukai.go.id, fiskal.kemenkeu.go.id, komdigi.go.id*

## PV-11: Redaksi ayat pengecualian PP 71/2019 Pasal 20 (pemrosesan data di luar wilayah NKRI)

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PP 71 Tahun 2019 tentang Penyelenggaraan Sistem dan Transaksi Elektronik (PSTE), Pasal 20
- **Teks/Angka Persis:**
  > "Setiap Penyelenggara Sistem Elektronik wajib melakukan pemrosesan Data Pribadi di wilayah hukum Indonesia."
  
  Pengecualian diatur dalam penjelasan Pasal 20 PP 71/2019 dan merujuk pada Pasal 56 UU 27/2022 tentang Pelindungan Data Pribadi (PDP). Berdasarkan PP 71/2019, pemrosesan data pribadi wajib dilakukan di wilayah Indonesia dengan pengecualian sebagaimana diatur dalam peraturan perundang-undangan. UU PDP Pasal 56 memberikan pengecualian apabila: (a) negara tujuan memiliki tingkat perlindungan yang setara atau lebih tinggi; (b) terdapat pengikatan diri yang memadai dan mengikat secara hukum; atau (c) terdapat persetujuan tertulis dari Subjek Data Pribadi.
- **Sumber:** 
  - [PP 71/2019 - Peraturan BPK](https://peraturan.bpk.go.id/Details/122030/pp-no-71-tahun-2019) (diakses 1 Nov 2026)
  - [UU 27/2022 - Peraturan BPK](https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022) (diakses 1 Nov 2026)
- **Dampak ke Sistem:** Wajib memastikan penyimpanan/pemrosesan data pribadi konsignee (nama, alamat, kontak) berada di server Indonesia. Jika menggunakan cloud/SaaS luar negeri, wajib dokumentasikan salah satu pengecualian Pasal 56 UU PDP (negara setara, pengikatan diri, atau persetujuan tertulis subjek data). Preferensi: gunakan infrastruktur on-premise/server Indonesia.

## PV-12: Status kelembagaan lembaga pengawas pelindungan data pribadi (UU PDP 27/2022) per Nov 2026

- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** UU 27 Tahun 2022 tentang Pelindungan Data Pribadi, Pasal 58 (mengenai Lembaga Pelindungan Data Pribadi)
- **Teks/Angka Persis:** UU PDP Pasal 58 mengamanatkan pembentukan Lembaga Pelindungan Data Pribadi yang bersifat independen. Hingga November 2026, belum ditemukan Perpres pembentukannya yang telah diundangkan secara resmi di JDIH Kemenkumham/Kemenkeu. Status pembentukan masih dalam proses perumusan.
- **Sumber:**
  - [UU 27/2022 - Peraturan BPK](https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022) (diakses 1 Nov 2026)
  - Pencarian JDIH Kemenkumham (diakses 1 Nov 2026) - tidak ditemukan Perpres terkait
- **Dampak ke Sistem:** Belum ada otoritas pelaksana yang aktif untuk pengaduan/penegakan secara penuh. Namun kewajiban pelindungan data tetap berlaku (Pasal 46: pelaporan pelanggaran 3x24 jam ke subjek data dan otoritas yang berwenang bila telah ada). Persiapkan mekanisme pelaporan internal dan dokumentasi kepatuhan.

## PV-13: Redaksi Pasal 29 UU Kepabeanan (UU 10/1995 jo. 17/2006) ayat (3) dan (4) — tanggung jawab PPJK atas pemberitahuan pabean

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** UU 10 Tahun 1995 tentang Kepabeanan jo. UU 17 Tahun 2006 tentang Perubahan atas UU 10/1995, Pasal 29 ayat (1)-(4)
- **Teks/Angka Persis:**
  > **Pasal 29**
  > (1) Pengurusan pemberitahuan pabean untuk kepentingan importir atau eksportir dapat dilakukan sendiri oleh importir atau eksportir atau dikuasakan kepada pengusaha pengurusan jasa kepabeanan.
  > (2) Pengusaha pengurusan jasa kepabeanan sebagaimana dimaksud dalam ayat (1) harus terdaftar pada Direktorat Jenderal Bea dan Cukai.
  > (3) Dalam hal pengurusan sebagaimana dimaksud dalam ayat (1) dikuasakan kepada pengusaha pengurusan jasa kepabeanan, pengusaha pengurusan jasa kepabeanan bertanggung jawab atas kebenaran pemberitahuan pabean yang dibuatnya.
  > (4) Pengusaha pengurusan jasa kepabeanan yang bertanggung jawab sebagaimana dimaksud dalam ayat (3) dapat dikenai sanksi administrasi berupa denda paling banyak Rp 100.000.000,00 (seratus juta rupiah).
- **Sumber:**
  - [UU 10/1995 - JDIH Kemenkeu](https://jdih.kemenkeu.go.id/api/download/FullText/1995/10TAHUN~1995UUHal3.htm) (diakses 1 Nov 2026)
  - [UU 17/2006 - Peraturan BPK](https://peraturan.bpk.go.id/Details/40211/uu-no-17-tahun-2006) (diakses 1 Nov 2026)
- **Dampak ke Sistem:** Klausul kuasa PPJK harus mencantumkan tanggung jawab atas kebenaran pemberitahuan pabean (klasifikasi HS, nilai pabean, kode lartas). Meskipun tanggung jawab pembayaran pada prinsipnya importir, PPJK dapat dikenai sanksi administrasi. Perlu tracking siapa yang merekam PIB (PPJK/importir) dan audit trail perubahan data.

## PV-14: Detail teknis Layanan API Nilai Kurs Kemenkeu

- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** PMK 66/PMK.03/2017 tentang Nilai Tukar Kurs Pajak
- **Teks/Angka Persis:** Kurs pajak ditetapkan mingguan dan berlaku Rabu–Selasa. KMK kurs berlaku untuk perhitungan pajak dalam rangka impor (PPN, PPh 22 impor, bea masuk terkait kurs). API tersedia di situs fiskal.kemenkeu.go.id namun dokumentasi teknis (endpoint spesifik, parameter, format response, mekanisme registrasi token) tidak terekspos secara publik di halaman landing.
- **Sumber:**
  - [Layanan API Nilai Kurs Kemenkeu](https://fiskal.kemenkeu.go.id/layanan/layanan-api-nilai-kurs) (diakses 1 Nov 2026)
  - [Kurs Pajak - Fiskal Kemenkeu](https://fiskal.kemenkeu.go.id/informasi-publik/kurs-pajak) (diakses 1 Nov 2026)
  - [PMK 66/PMK.03/2017 - JDIH Kemenkeu](https://jdih.kemenkeu.go.id/) (perlu unduh PDF resmi untuk konfirmasi)
- **Dampak ke Sistem:** Untuk integrasi otomatis, perlu registrasi langsung ke Kemenkeu/Fiskal untuk memperoleh akses API dan dokumentasi teknis. Alternatif: scrape/download Excel dari halaman kurs pajak atau simpan snapshot KMK per periode Rabu–Selasa. Scheduler wajib mengganti kurs setiap hari Rabu.

## PV-15: CEISA 4.0 Open API — otorisasi kuasa importir→PPJK, jenis credential, endpoint utama PIB

- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** Peraturan terkait CEISA 4.0 (belum ditemukan satu nomor PMK/Perdirjen terpusat yang dipublikasikan secara lengkap di web publik)
- **Teks/Angka Persis:** 
  - Otorisasi kuasa importir→PPJK dilakukan melalui Portal CEISA 4.0 (akun per badan usaha). PPJK merekam PIB atas nama importir setelah mendapat otorisasi.
  - Tersedia tiga peran: (a) Pengguna API sekaligus Pengguna Jasa, (b) Pengguna Jasa yang mengakses via penyedia API, (c) Penyedia API (vendor).
  - Endpoint utama untuk dokumen impor (PIB) tersedia dalam dokumentasi Open API CEISA 4.0, namun detail teknis (base URL, auth method, scope, endpoint list) memerlukan akses dokumentasi resmi yang hanya tersedia setelah registrasi/akreditasi sebagai Pengguna API di DJBC.
- **Sumber:**
  - [CEISA 4.0 Open API Documentation (OneWebFile)](https://onewebfile.beacukai.go.id/cdn/download/file/685a4669846e515ab1d90c72) (diakses 1 Nov 2026 - file tersedia)
  - [User Manual CEISA 4.0](https://usermanualceisa40.gitbook.io/portal-ceisa40) (diakses 1 Nov 2026)
- **Dampak ke Sistem:** Untuk host-to-host, wajib melalui proses registrasi teknis dengan DJBC. Sistem perlu mendukung dua mode: input manual via portal atau integrasi API. Simpan mapping user/kuasa dan audit trail otorisasi.

## PV-16: Apakah DJBC masih mempublikasikan daftar nilai pabean minimum per HS (PMK 122/2021 dsb)?

- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** PMK 122/PMK.04/2021 tentang Penetapan Tarif Bea Masuk atas Barang Impor Berdasarkan Data Wajib Pabean
- **Teks/Angka Persis:** PMK 122/2021 merupakan penerus mekanisme penetapan tarif/nilai berdasarkan data wajib bea masuk. Publikasi daftar nilai pabean minimum per HS tidak ditemukan secara terbuka di situs beacukai.go.id dalam bentuk daftar umum yang dapat diunduh per November 2026. Akses mungkin terbatas untuk pengguna berwenang atau melalui sistem internal DJBC.
- **Sumber:**
  - [PMK 122/PMK.04/2021 - Ortax](https://datacenter.ortax.org/ortax/aturan/show/17545) (diakses 1 Nov 2026)
  - [Peraturan Bea Cukai](https://peraturan.beacukai.go.id) (pencarian tidak menemukan publikasi daftar terbuka)
- **Dampak ke Sistem:** Jangan mengasumsikan adanya daftar nilai minimum umum yang dapat diunduh publik. Untuk kalkulasi nilai pabean, tetap menggunakan metode nilai transaksi sesuai PMK 144/PMK.04/2022 sebagai dasar utama.

## PV-17: Aturan retensi data elektronik impor 10 tahun — konfirmasi keberadaan "PMK 71/2026" atau dasar hukum pengawasan kepabeanan terbaru

- **Status:** MASIH TERBUKA
- **Dasar Hukum & Pasal:** UU Kepabeanan Pasal 117 (retensi 5 tahun) jo. UU KUP Pasal 28 ayat (1) huruf c (pembukuan 10 tahun). Tidak ditemukan PMK dengan nomor "71/2026" yang teridentifikasi pada pencarian publik per 1 Nov 2026.
- **Teks/Angka Persis:** 
  - UU Kepabeanan: dokumen pemberitahuan pabean dan pendukung wajib disimpan 5 tahun sejak tanggal pemberitahuan disampaikan.
  - UU KUP: buku, catatan, dokumen pembukuan wajib disimpan 10 tahun di Indonesia sejak akhir tahun buku.
- **Sumber:**
  - [UU 17/2006 - Peraturan BPK](https://peraturan.bpk.go.id/Details/40211/uu-no-17-tahun-2006) (Pasal 117)
  - [UU 7/2021 (HPP) perubahan UU KUP](https://peraturan.bpk.go.id/Details/185162/uu-no-7-tahun-2021) (diakses 1 Nov 2026)
  - Pencarian JDIH Kemenkeu untuk "PMK 71/2026" - tidak ditemukan
- **Dampak ke Sistem:** Terapkan kebijakan retensi **10 tahun** untuk arsip PIB, invoice, packing list, B/L, bukti pembayaran sebagai standar sistem (mengakomodasi kewajiban pajak yang lebih panjang). Siapkan mekanisme ekspor arsip sebelum penghapusan dan pertimbangkan kewajiban simpan dokumen elektronik untuk keperluan audit.

## PV-18: Regulasi registrasi PPJK pasca-2025 — status PMK 219/PMK.04/2019, PER-35/BC/2019, KEP-222/BC/2022

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** PMK 219/PMK.04/2019 tentang Penyederhanaan Perizinan di Bidang Kepabeanan; PER-35/BC/2019; KEP-222/BC/2022
- **Teks/Angka Persis:** 
  - PMK 219/PMK.04/2019 masih menjadi acuan utama. Importir/eksportir memperoleh akses kepabeanan otomatis via NIB (OSS). PPJK wajib terdaftar melalui Portal Pengguna DJBC (CEISA) dan wajib memiliki pegawai Ahli Kepabeanan.
  - Tidak ditemukan PMK pengganti yang diundangkan per 1 Nov 2026. Perubahan teknis kemungkinan melalui Perdirjen/Keputusan Dirjen, namun regulasi induk (PMK 219/2019) masih berlaku.
- **Sumber:**
  - [PMK 219/PMK.04/2019 - JDIH Kemenkeu](https://jdih.kemenkeu.go.id/api/download/fulltext/2019/219~PMK.04~2019Per.pdf) (diakses 1 Nov 2026)
  - [Registrasi Kepabeanan - DJBC](https://www.beacukai.go.id/registrasi-kepabeanan) (diakses 1 Nov 2026)
- **Dampak ke Sistem:** Validasi status PPJK tetap merujuk PMK 219/2019. Pastikan verifikasi keanggotaan Ahli Kepabeanan dan status terdaftar di CEISA. Monitor pengumuman DJBC terkait perubahan teknis.

## PV-19: Tanggal efektif berlaku penuh IA-CEPA dari sumber resmi

- **Status:** SEKUNDER-TERKONFIRMASI
- **Dasar Hukum & Pasal:** Indonesia-Australia Comprehensive Economic Partnership Agreement (IA-CEPA)
- **Teks/Angka Persis:** IA-CEPA telah berlaku efektif sejak **5 Juli 2020**. Kesepakatan ini telah diimplementasikan penuh sesuai jadwal bertahapnya. Tidak ada perubahan tanggal berlaku penuh baru per November 2026.
- **Sumber:**
  - [IA-CEPA - Kemendag](https://kemendag.go.id/) (informasi publik)
  - [FAQ Fasilitas FTA DJBC](https://www.beacukai.go.id/faq-fasilitas-fta) (menyebut IA-CEPA telah berlaku, diakses 1 Nov 2026)
- **Dampak ke Sistem:** Klaim preferensi Form IA-CEPA dapat digunakan untuk impor dari Australia dengan tarif preferensi sesuai skema yang berlaku. Pastikan validasi nomor SKA/e-Form dan periode berlakunya sesuai ketentuan.

---

**Catatan:** Beberapa item (PV-12, PV-14, PV-15, PV-16, PV-17) masih memerlukan verifikasi lebih lanjut ke dokumen PDF resmi atau klarifikasi langsung dari instansi terkait. Untuk implementasi produksi, disarankan mengunduh arsip PDF resmi dari JDIH sebagai arsip hukum proyek.
