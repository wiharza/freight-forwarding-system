# Riset Regulasi Kepabeanan dan Pajak Impor Indonesia

- **Tanggal riset:** 1 November 2026 (status per tanggal ini)
- **Tujuan:** Basis fakta untuk sistem web freight forwarder impor (kalkulator PIB, pemberitahuan pabean, retensi dokumen, integrasi sistem).
- **Metode:** Penelusuran web dengan prioritas sumber resmi (jdih.kemenkeu.go.id, peraturan.bpk.go.id, fiskal.kemenkeu.go.id, pajak.go.id, beacukai.go.id, kemendag.go.id), dikonfirmasi dengan artikel konsultan pajak (ORTAX, DDTC, MUC) bila sumber resmi tidak memuat teks pasal.
- **Kode tanda:**
  - `[PERLU VERIFIKASI]` = ditemukan hanya di sumber sekunder / nomor pasal belum terkonfirmasi dari teks resmi.
  - `[BELUM DIVERIFIKASI]` = tidak ditemukan sumber yang memadai saat riset.
- **Catatan umum:** Teks pasal yang dikutip berasal dari fulltext/halaman resmi yang berhasil dibaca; untuk implementasi produksi, tetap unduh PDF resmi dari JDIH sebagai arsip hukum proyek.

---

## Ringkasan cepat (tabel status)

| # | Topik | Dasar hukum terkini (per Nov 2026) | Status |
|---|-------|-------------------------------------|--------|
| 1 | PPN impor | UU HPP 7/2021; PMK 131/2024 → digantikan mekanisme **PMK 11/2025 jo. PMK 53/2025** | 12% hanya barang mewah; umum efektif 11% via DPP nilai lain 11/12 |
| 2 | PPh 22 impor | **PMK 51/2025** (berlaku 1 Agu 2025), menggantikan PMK 34/PMK.010/2017 jo. PMK 41/PMK.010/2022 | Tarif 0,25%–10% per lampiran; 2,5% umum dengan API; 7,5% tanpa API |
| 3 | PPnBM impor | PP 73/2019 + PMK 96/PMK.03/2021 + PMK 141/PMK.010/2021 | Dihitung dari **Nilai Impor** (bukan nilai pabean) |
| 4 | Kurs pajak | PMK 66/PMK.03/2017; KMK mingguan | Berlaku **Rabu–Selasa** (bukan Senin berikutnya); ada **API resmi** |
| 5 | Nilai pabean | **PMK 144/PMK.04/2022** (bukan PMK 203/2017) | Asuransi 0,5% × FOB bila tidak diketahui |
| 6 | Pembulatan / BM minimal | KMK 584/KMK.05/1996 (aktif); PP 84/2001 (rezim lama) | Pembulatan ke atas ribuan penuh; Rp25.000 `[PERLU VERIFIKASI]` |
| 7 | Retensi dokumen | UU Kepabeanan Ps. 117 (5 th); UU KUP Ps. 28 ayat (1) huruf c (10 th) | Gunakan 10 tahun sebagai standar sistem |
| 8 | PPJK | UU 10/1995 Ps. 29 jo. UU 17/2006; PMK 65/PMK.04/2007; PMK 219/PMK.04/2019 | Registrasi via CEISA, wajib Ahli Kepabeanan |
| 9 | FTA/Form | 10 skema berlaku; IEU-CEPA **belum berlaku** (target 2027); GSP AS matang sejak 2020 | Lihat tabel di Bagian 9 |
| 10 | CEISA 4.0 | Open API DJBC (host-to-host) | Tersedia, perlu registrasi Portal CEISA + otorisasi kuasa |
| 11 | API-U vs API-P | UU Cipta Kerja; PMK 219/PMK.04/2019; Permendag 36/2023 | NIB = API via OSS; berdampak ke tarif PPh 22 |
| 12 | BTKI | PMK 26/PMK.010/2022 (BTKI 2022); diubah PMK 62/2025 `[PERLU VERIFIKASI]` | Belum ada kitab baru "BTKI 2025/2026" |
| 13 | UU PDP / PP 71/2019 | UU 27/2022 (Ps. 20, 55, 56, 46); PP 71/2019 (Ps. 20) | Berlaku penuh sejak Okt 2024 |

---

## 1. PPN Impor 2025–2026

**Fakta terverifikasi:**

1. Tarif PPN nominal 12% berlaku sejak 1 Januari 2025 (UU HPP 7/2021, Pasal 6 ayat (1) huruf b: kenaikan bertahap mulai 1 Januari 2025).
2. **PMK 131/2024** (berlaku 1 Januari 2025) membebankan tarif 12% **penuh hanya atas barang mewah** (termasuk kendaraan bermotor) — Pasal 2 ayat (3); untuk barang/jasa umum digunakan **DPP Nilai Lain 11/12** sehingga beban efektif tetap **11%** — Pasal 5–6. Untuk impor BKP non-mewah, nilai lain 11/12 dihitung dari **nilai impor** (Pasal 2 ayat (3) PMK 131/2024).
3. Mekanisme DPP nilai lain kini dituangkan ulang dalam **PMK 11 Tahun 2025** ("Ketentuan Nilai Lain sebagai Dasar Pengenaan Pajak dan Besaran Tertentu Pajak Pertambahan Nilai"), yang kemudian diubah oleh **PMK 53 Tahun 2025** (diundangkan 28 Juli 2025, berlaku 1 Agustus 2025 — menghapus PPN aset kripto, **mempertahankan faktor 11/12** untuk ketentuan lainnya).
4. **Status November 2026:** skema ini masih berlaku — 12% penuh untuk barang mewah, efektif 11% (12% × 11/12) untuk barang umum. Analisis DDTC (Januari 2026) masih membahas skema DPP 11/12 sebagai kebijakan yang berjalan, dan Kemenkeu menegaskan tidak ada perubahan tarif untuk barang umum pada 2026. `[PERLU VERIFIKASI]` apakah ada PMK pengganti PMK 11/2025 terbit akhir 2026.
5. DPP PPN impor = **Nilai Impor** (UU PPN jo. UU HPP, Pasal 11 ayat (5)). Nilai Impor = nilai dasar penghitungan bea masuk (CIF) + bea masuk + pungutan lainnya.

**Implikasi kalkulator:**
- PPN impor umum: `12% × (11/12) × Nilai Impor` ≡ `11% × Nilai Impor` (hasil sama, tapi legal footprint di PIB/e-Faktur memakai DPP nilai lain 11/12 — penting jika sistem menampilkan rincian DPP).
- PPN impor barang mewah (kendaraan bermotor dsb.): `12% × Nilai Impor` penuh.

**Sumber:**
- UU 7/2021 (HPP): https://peraturan.bpk.go.id (UU 7/2021)
- PMK 131/2024 (fulltext JDIH): https://jdih.kemenkeu.go.id/api/download/f128868e-3cf6-4596-8407-c34eeca0e7be/2024pmkeuangan131.pdf
- Artikel resmi DJP "PMK 131/2024: Tarif PPN Sebelas-Dua Belas": https://pajak.go.id/id/artikel/pmk-1312024-tarif-ppn-sebelas-dua-belas
- PMK 53/2025: https://jdih.kemenkeu.go.id/dok/pmk-53-tahun-2025 ; https://peraturan.bpk.go.id/Details/326338/pmk-no-53-tahun-2025
- Analisis: https://ortax.org/resmi-pmk-131-2024-atur-ppn-12-persen-hanya-untuk-barang-mewah ; https://news.ddtc.co.id/berita/nasional/1812538/aset-kripto-kini-tak-kena-ppn-pmk-112025-direvisi

---

## 2. PPh 22 Impor

**KOREKSI PREMIS:** PMK **37/PMK.03/2022** bukan dasar PPh 22 impor — itu perubahan atas PMK 240/PMK.03/2014 (PPh 22 **domestik**, barang tertentu seperti baja, rokok, kertas). Dasar PPh 22 impor yang lama adalah **PMK 34/PMK.010/2017 jo. PMK 110/PMK.010/2018 jo. PMK 41/PMK.010/2022**, dan sejak **1 Agustus 2025** digantikan oleh **PMK 51 Tahun 2025** ("Pemungutan Pajak Penghasilan Pasal 22 Sehubungan dengan Pembayaran atas Penyerahan Barang dan Kegiatan di Bidang Impor atau Kegiatan Usaha di Bidang Lain"; ditetapkan 25 Juli 2025).

**Tarif PPh 22 impor menurut PMK 51/2025 (Pasal 3 ayat (1), dikutip dari fulltext ORTAX):**

| Tarif | Cakupan | API |
|-------|---------|-----|
| 10% | Barang tertentu — Lampiran A (mis. mineral logam) | dengan atau tanpa API |
| 7,5% | Barang tertentu lainnya — Lampiran B | dengan atau tanpa API |
| 0,5% | Kedelai, gandum, tepung terigu | dengan API |
| 0,25% | Emas batangan | dengan atau tanpa API |
| 2,5% | Barang selain daftar di atas (kategori umum) | **dengan API** |
| 7,5% | Kategori umum/daftar tertentu **tanpa API** | tanpa API |
| 7,5% | Barang yang tidak dikuasai (dari harga jual lelang) | — |

- **Definisi nilai impor** (Pasal 3 ayat (2)): "nilai berupa uang yang menjadi dasar penghitungan bea masuk yaitu cost insurance and freight ditambah dengan bea masuk dan pungutan lainnya" → **CIF + BM + pungutan lain**, bukan CIF mentah.
- PPh 22 impor terutang dan dilunasi pada saat pembayaran bea masuk (Pasal 7 ayat (1) PMK 51/2025, konfirmasi Kring Pajak DJP).
- **Pengecualian utama** (Pasal 4 ayat (1)): impor yang dibebaskan dari bea masuk dan/atau PPN (±19 kategori: perwakilan negara asing, hibah bencana, buku, barang keperluan hulu migas/panas bumi, dsb.), impor sementara untuk diekspor kembali, impor kembali untuk perbaikan/pengerjaan, dan pengecualian pembayaran tertentu oleh pemungut (mis. pembelian ≤ Rp2 juta oleh instansi pemerintah, ≤ Rp20 juta bahan hasil alam per masa pajak).
- **Ambang USD 50:** pada rezim lama (PMK 34/2017 jo. PMK 41/2022) pemungutan tidak dilakukan atas impor dengan **nilai FOB** sampai dengan USD 50 (yaitu berbasis FOB, bukan nilai impor). Pada fulltext PMK 51/2025 yang terbaca **tidak ditemukan lagi ketentuan USD 50** — pengecualian bernilai sekarang hanya untuk kiriman barang (rezim PMK 96/2023, lihat Bagian 6). `[PERLU VERIFIKASI]` — cek lampiran/pasal pengecualian PMK 51/2025 dari PDF resmi sebelum memutuskan menghapus threshold ini dari kalkulator.

**Sumber:**
- PMK 51/2025 (metadata & abstrak JDIH): https://jdih.kemenkeu.go.id/dok/pmk-51-tahun-2025
- PMK 51/2025 (fulltext ORTAX): https://datacenter.ortax.org/ortax/aturan/show/26357
- PMK 51/2025 (BPK): https://peraturan.bpk.go.id/Details/326296/pmk-no-51-tahun-2025
- PMK 37/PMK.03/2022 (PPh 22 domestik): https://jdih.kemenkeu.go.id/pdfjs/web/viewer.html?file=/api/download/6be54fc0-dc96-4ae3-ac3b-2ffcdf27774e/132~PMK.03~2022.pdf
- PMK 41/PMK.010/2022 (rezim lama impor): https://jdih.kemenkeu.go.id/api/download/5c9a0378-4dd8-4a5b-9416-154f51251f64/41_PMK.010_2022.pdf

---

## 3. PPnBM Impor

**Struktur dasar pengenaan (terverifikasi dari kerangka UU PPN):**

1. PPnBM atas BKP impor menggunakan **Dasar Pengenaan Pajak = Nilai Impor** (UU PPN Pasal 16D ayat (2)) — **bukan Nilai Pabean** (CIF). Nilai Impor = CIF + BM + pungutan lainnya. Jadi PPnBM impor dihitung **di atas bea masuk yang sudah dibayar**, sama seperti PPN impor dan PPh 22 impor.
2. Tarif PPnBM: 10%, 20%, 30%, 40%, 50%, atau 75% (UU PPN Pasal 16D ayat (1)).
3. **PP 73/2019** ("Tarif Pajak Penjualan atas Barang Mewah yang Tidak Dikenai Bea Tambahan atas Barang Ekspor") menetapkan tarif per kategori barang mewah dan **ambang Nilai Impor (NI)** untuk barang mewah tertentu yang diimpor: kendaraan bermotor (NI > USD 30.000), pesawat udara niaga (NI > USD 50.000), kapal/yacht (NI > USD 50.000), rumah/hunian (NI > USD 30.000). `[PERLU VERIFIKASI]` angka ambang persis dari teks PP 73/2019 (dikutip dari sumber sekunder).
4. Daftar barang mewah + tarif diperinci dalam **PMK 96/PMK.03/2021** (tarif 20/40/50/75%: hunian mewah, pesawat udara, senjata api, dll.) dan **PMK 141/PMK.010/2021** (kendaraan bermotor listrik kabin ganda tertentu).

**Kategori barang mewah utama yang dikenai PPnBM:** kendaraan bermotor di atas ambang; pesawat udara niaga; kapal/yacht; rumah/hunian mewah; senjata api; serta barang mewah lain per PMK 96/2021. **Untuk kargo umum (B2B freight forwarder), PPnBM = 0** kecuali HS termasuk daftar ini.

**Sumber:**
- PP 73/2019: https://peraturan.bpk.go.id (PP 73/2019)
- PMK 96/PMK.03/2021: https://jdih.kemenkeu.go.id/dok/96-pmk-03-2021/summary
- PMK 141/PMK.010/2021: https://datacenter.ortax.org/ortax/aturan/show/17561
- Penjelasan: https://klikpajak.id/blog/hal-dasar-tentang-ppnbm ; https://www.pbtaxand.com/menu/detail/whats_new/783/ppnbm-dan-cara-menghitungnya

---

## 4. Kurs Pajak (KMK)

**Fakta terverifikasi (halaman resmi fiskal.kemenkeu.go.id, dibaca 1 Nov 2026):**

1. Dasar: **PMK 66/PMK.03/2017** tentang Nilai Tukar Kurs Pajak (KMK mingguan dirujuk berdasarkan PMK ini dan perubahannya).
2. **Mekanisme berlaku saat ini:** KMK Kurs ditetapkan **setiap pekan dan berlaku 1 pekan mulai hari Rabu sampai Selasa** (bukan "diterbitkan Rabu, berlaku Senin berikutnya" seperti premis lama). Contoh riil: KMK 51/MK/EF.2/2026, USD 18.032,00 (+86,00), **berlaku 28 Oktober – 3 November 2026**. Saat libur Idulfitri, KMK berlaku 2 pekan.
3. Sumber resmi: https://fiskal.kemenkeu.go.id/informasi-publik/kurs-pajak (mirror: https://pajak.go.id/id/kurs-pajak). Tersedia tombol **Download Excel** dan arsip KMK (https://fiskal.kemenkeu.go.id/peraturan/kmk-kurs-pajak).
4. **API resmi ADA:** "Layanan API Nilai Kurs" di https://fiskal.kemenkeu.go.id/layanan/layanan-api-nilai-kurs — biaya Rp0 (tidak dipungut biaya). Detail endpoint/dokumentasi teknis tidak terekspos di landing page (halaman render JS) `[PERLU VERIFIKASI]` untuk detail endpoint & registrasi token. Situs juga punya peta situs: https://fiskal.kemenkeu.go.id/beranda/peta-situs.
5. `[PERLU VERIFIKASI]` pasal PMK 66/2017 yang memuat periodisasi — kutipan FAQ resmi menyebut Rabu–Selasa, sedangkan banyak sumber lama menyebut siklus Senin–Minggu; kemungkinan ada perubahan PMK (belum ditemukan nomornya).

**Implikasi kalkulator:** simpan snapshot KMK per periode Rabu–Selasa; PIB didaftarkan Senin–Selasa memakai KMK yang sama; ganti KMK otomatis setiap Rabu (hari perubahan).

---

## 5. Nilai Pabean

**KOREKSI PREMIS:** Dasar hukum nilai pabean yang berlaku adalah **PMK 144/PMK.04/2022 tentang Nilai Pabean untuk Perhitungan Bea Masuk** (ditetapkan 18 Oktober 2022), yang menggantikan PMK 160/PMK.04/2010 jo. PMK 34/PMK.04/2016. **PMK 203/PMK.04/2017 itu bukan regulasi nilai pabean umum** — isunya "Ketentuan Ekspor dan Impor Barang yang Dibawa Penumpang atau Awak Sarana Pengangkut" (barang bawaan pribadi/jastip/kiriman), yang ketentuan barunya ada di PMK 126/2023.

**Fakta:**

1. Metode utama: **nilai transaksi** (harga yang benar-benar dibayar atau akan dibayar) — UU Kepabeanan Pasal 15; metode sekunder (transaksi identik, komersial identik, deduksi, nilai hitung, fallback) — UU Kepabeanan Pasal 16 dan PMK 144/2022.
2. **Asuransi:** apabila biaya asuransi **tidak dapat diketahui/dibuktikan**, digunakan **0,5% × nilai FOB** (PMK 144/PMK.04/2022 — konfirmasi kutipan dari beberapa sumber; `[PERLU VERIFIKASI]` nomor pasal persis, umumnya di bagian metode nilai transaksi). Praktik terkait: asuransi ditutup di dalam negeri → dianggap 0; kondisi CFR dengan asuransi tak diketahui → 0,5% × CFR (praktik edukasi DJBC).
3. **Konversi mata uang non-USD:** FOB/freight/asuransi dalam mata uang asing dikonversi memakai **kurs yang ditetapkan Menteri Keuangan (kurs pajak) pada saat pendaftaran PIB** (PMK 144/2022; sinkron PMK 66/2017 — Bagian 4).
4. **"Batas bawah nilai pabean":** tidak ditemukan ketentuan "minimum customs value" umum dalam PMK 144/2022. Yang ada: mekanisme **penetapan tarif BM atas dasar data wajib bea masuk** (PMK 122/PMK.04/2021, penerus PMK 229/PMK.04/2017 jo. PMK 11/PMK.04/2019) yang dalam praktiknya dipakai DJBC untuk menetapkan tarif/nilai atas impor tertentu (termasuk dulu terkait kiriman ≤ USD 1.500 — dasar: PMK 112/PMK.04/2018). `[PERLU VERIFIKASI]` apakah ada daftar nilai pabean minimum per HS yang masih dipublikasikan DJBC.

**Sumber:**
- PMK 144/PMK.04/2022: https://peraturan.info/permenkeu/2022/144/info ; direktori https://peraturan.beacukai.go.id ; https://peraturan.bpk.go.id (PMK 144/PMK.04/2022)
- PMK 203/PMK.04/2017 (barang penumpang): https://jdih.kemenkeu.go.id
- PMK 122/PMK.04/2021: https://datacenter.ortax.org/ortax/aturan/show/17545
- PMK 112/PMK.04/2018: https://peraturan.beacukai.go.id

---

## 6. Bea Masuk: Pembulatan dan BM Minimal

1. **Pembulatan:** jumlah bea masuk (dan pajak dalam rangka impor) **dibulatkan ke atas dalam ribuan penuh**. Dasar yang masih berstatus "Aktif" di katalog pajak.go.id: **KMK 584/KMK.05/1996** tentang Tata Cara Pembulatan Jumlah Bea Masuk, Denda Administrasi, Bunga, dan Pajak dalam rangka Impor (ditetapkan 23-09-1996). Untuk rezim dokumen tertentu, pembulatan juga diulang dalam PMK pelaksana (contoh: **PMK 190/2022 Pasal 22 ayat (4)** — pembulatan ribuan ke atas per seri barang dalam PIB; lihat DDTC News). Berlaku juga untuk BM antidumping/tindakan pengamanan/imbalan/pembalasan.
2. **BM di bawah Rp25.000 tidak dipungut:** premis ini berasal dari rezim lama — dikutip pada **PP 84/2001** (Tata Cara Pengenaan Bea Masuk atas Barang Impor, Pasal 6 ayat (3)): "Bea masuk tidak dipungut apabila jumlahnya kurang dari Rp25.000,00." `[PERLU VERIFIKASI]` — tidak ditemukan peraturan yang masih berlaku (era UU 17/2006) yang mengulang angka Rp25.000 secara eksplisit; sebaiknya jangan dijadikan aturan kalkulator tanpa konfirmasi ke DJBC.
3. **De minimis yang jelas saat ini (terverifikasi):** untuk **barang kiriman** (pos/kargo express) — **PMK 96 Tahun 2023** (ditetapkan 15 September 2023): kiriman dengan nilai ≤ **USD 3** → bea masuk dibebaskan, **PPN tetap dipungut**, **PPh 22 tidak dipungut**; tarif kiriman non-official-assessment 7,5%–15% per kategori. (Sejarah: USD 100 → USD 75 (2018) → USD 3 per 30 Januari 2020.)

**Sumber:**
- KMK 584/KMK.05/1996 (metadata aktif): https://www.pajak.go.id/id/peraturan/tata-cara-pembulatan-jumlah-bea-masuk-denda-administrasi-bunga-dan-pajak-dalam-rangka
- PMK 190/2022 (pembulatan per seri barang, DDTC): https://news.ddtc.co.id/berita/nasional/1797874/bea-masuk-dan-cukai-impor-dibulatkan-ribuan-ke-atas-begini-contohnya
- PMK 96/2023 (fulltext): https://jdih.kemenkeu.go.id/api/download/bab0f601-a192-4451-b99b-8bb9f6edd4c0/2023pmkeuangan096abs.pdf ; https://peraturan.beacukai.go.id (PMK 96 Tahun 2023)

---

## 7. Retensi Dokumen

1. **Dokumentasi kepabeanan — 5 tahun:** orang yang menyampaikan pemberitahuan pabean (dan pihak yang membuktikan kebenaran penghitungan/pembayaran) wajib menyimpan dokumen/data pemberitahuan pabean beserta dokumen pendukung (invoice, packing list, B/L) selama **5 (lima) tahun** sejak tanggal pemberitahuan disampaikan — **Pasal 117 UU Kepabeanan (UU 10/1995 jo. UU 17/2006)**. Ketentuan dokumentasi untuk pengawasan modern diatur juga di PMK 216/PMK.04/2022 (definisi dokumentasi kepabeanan). `[PERLU VERIFIKASI]` terdapat pembahasan 2025–2026 tentang kewajiban simpan **data elektronik impor hingga 10 tahun** pada aturan pengawasan terbaru (nomor PMK belum teridentifikasi; indikasi adanya "PMK 71/2026" belum terkonfirmasi).
2. **Pembukuan pajak — 10 tahun:** **Pasal 28 ayat (1) huruf c UU KUP (UU 28/2007 jo. perubahan terakhir UU 7/2021/HPP)**: buku, catatan, dan dokumen yang menjadi dasar pembukuan/pencatatan **wajib disimpan di Indonesia selama 10 (sepuluh) tahun**, dihitung sejak akhir tahun buku tempat dokumen itu wajib tersedia. **Koreksi premis:** penomorannya adalah Pasal 28 ayat (1) huruf c pada UU 28/2007 (angka "ayat (8)" adalah penomoran era UU 16/2000 yang lama).
3. **Implikasi sistem:** gunakan **retensi 10 tahun** untuk arsip digital (PIB, invoice, B/L, bukti bayar) — memenuhi kewajiban pajak dan menutup kewajiban kepabeanan; beri kemampuan ekspor arsip sebelum penghapusan.

**Sumber:**
- UU 17/2006 (fulltext): https://jdih.kemenkeu.go.id/api/download/FullText/2006/17TAHUN2006UU.htm ; https://peraturan.bpk.go.id/Download/29824/UU%20Nomor%2017%20Tahun%202006.pdf
- UU KUP: https://www.pajak.go.id/id/undang-undang-nomor-28-tahun-2007 ; https://datacenter.ortax.org/ortax/aturan/show/12761
- PMK 216/PMK.04/2022: https://peraturan.beacukai.go.id

---

## 8. PPJK (Pengusaha Pengurusan Jasa Kepabeanan)

1. **Dasar hukum:** Pasal 29 UU 10/1995 jo. UU 17/2006 — ayat (1) pengurusan pemberitahuan pabean oleh pengangkut/importir/eksportir; **ayat (2) pengurusan dapat dikuasakan kepada PPJK yang terdaftar di Kantor Pabean**. PPJK = badan usaha yang mengurus pemenuhan kewajiban pabean **untuk dan atas kuasa** importir/eksportir.
2. **Perizinan/registrasi (terkini):**
   - **PMK 65/PMK.04/2007** tentang PPJK (registrasi klasik).
   - **PMK 219/PMK.04/2019** tentang Penyederhanaan Perizinan Kepabeanan (masih menjadi acuan; tidak ditemukan PMK pengganti per Nov 2026): importir/eksportir memperoleh akses kepabeanan otomatis via **NIB (OSS)**; **PPJK mendaftar melalui Portal Pengguna DJBC (CEISA)** dan **wajib memiliki pegawai Ahli Kepabeanan**.
   - Teknis: PER-35/BC/2019 (dirjen BC) dan KEP-222/BC/2022. `[PERLU VERIFIKASI]` update teknis terbaru pas-2025.
3. **SIKA:** Sistem Informasi Kepabeanan — istilah lama untuk sistem pemberitahuan elektronik; kini berfungsi dalam ekosistem **CEISA 4.0** (PIB/PEB elektronik, lihat Bagian 10).
4. **Tanggung jawab hukum:** PPJK yang diberi kuasa **bertanggung jawab atas kebenaran/kelengkapan pemberitahuan pabean** (PIB: klasifikasi HS, nilai pabean, kode lartas, data pengirim/penerima) yang direkam atas nama importir; kewajiban pembayaran bea masuk/pungutan secara prinsip tetap pada importir namun PPJK ikut menanggung tanggung jawab pemberitahuan secara personal terhadap DJBC (UU Kepabeanan Pasal 29; penguasa barang dapat dituntut BM kurang bayar — Pasal 85 UU Kepabeanan). `[PERLU VERIFIKASI]` redaksi ayat persis Pasal 29 ayat (3)/(4) untuk kebutuhan kontrak kuasa.

**Sumber:**
- UU 10/1995 Pasal 29: https://pasal.id/peraturan/uu/uu-no-10-tahun-1995/pasal-29 ; fulltext https://jdih.kemenkeu.go.id/api/download/fulltext/1995/10TAHUN~1995UUHal3.htm
- PMK 65/PMK.04/2007: https://peraturan.beacukai.go.id/index.html?page=detail/tag/23/111/peraturan-menteri-keuangan/per-65-pmk-04-2007/pengusaha-pengurusan-jasa-kepabeanan-ppjk.html
- PMK 219/PMK.04/2019 (fulltext): https://jdih.kemenkeu.go.id/api/download/fulltext/2019/219~PMK.04~2019Per.pdf
- Registrasi kepabeanan: https://www.beacukai.go.id/registrasi-kepabeanan

---

## 9. Skema FTA/Form Preferensi untuk Importir Indonesia (status per Nov 2026)

| Skema | Mitra | Form/SKA | Status per Nov 2026 | Catatan |
|-------|-------|----------|---------------------|---------|
| ACFTA | ASEAN–China | **Form E** | BERLAKU | e-Form E; kode fasilitas + no. referensi SKA di PIB |
| ATIGA | ASEAN | **Form D** | BERLAKU | e-Form D |
| AKFTA | ASEAN–Korea | **Form AK** | BERLAKU | e-Form AK |
| AIFTA | ASEAN–India | **Form AI** | BERLAKU | pelaksanaan impor via PMK 35/2023 (sebelumnya PMK 170/PMK.04/2020) `[PERLU VERIFIKASI nomor]` |
| AANZFTA | ASEAN–Australia–Selandia Baru | **Form ANZ** | BERLAKU | termasuk e-Form ANZ `[PERLU VERIFIKASI]` |
| IJEPA | Indonesia–Jepang | **Form IJEPA** | BERLAKU | e-SKA resmi sejak 26 Juni 2023 |
| IA-CEPA | Indonesia–Australia | **Form IA-CEPA** | BERLAKU (sejak 5 Juli 2020) | `[PERLU VERIFIKASI]` tanggal efektif dari sumber resmi |
| IC-CEPA | Indonesia–Chili | **Form IC** | BERLAKU (sejak 10 Agustus 2019; bab jasa 2025) | Permendag 59/2019 |
| RCEP | RCEP (15 negara) | **Form RCEP** | BERLAKU (Indonesia sejak 1 Januari 2022) | SKA Form RCEP |
| IEU-CEPA | Indonesia–Uni Eropa | (belum ada Form) | **BELUM BERLAKU** | Ditandatangani September 2025; ratifikasi DPR target semester II 2026; target berlaku efektif **1 Januari 2027**. Tidak ada "Form MCEPA" untuk Indonesia (MCEPA = Malaysia–UE) |
| GSP | AS (unilateral, sisi EKSPOR) | — | **TIDAK AKTIF** | Program GSP AS kedaluwarsa 31 Des 2020, belum diperpanjang; penggantinya Agreement on Reciprocal Trade RI–AS (ditandatangani 19–20 Februari 2026, tarif 19%). GSP bukan skema preferensi IMPOR ke Indonesia |

- **Dasar hukum klaim preferensi di PIB:** **PMK 88 Tahun 2023** (Tata Cara Pengenaan Tarif BM atas Barang Impor Berdasarkan Perjanjian/Kesepakatan Internasional — menetapkan tarif preferensi periode 1 Januari 2023–31 Desember 2027 untuk berbagai skema) `[PERLU VERIFIKASI]` apakah sudah ada pembaruan 2026.
- **e-SKA Kemendag** (https://ska.kemendag.go.id / https://e-ska.kemendag.go.id): SKA elektronik ber-STempel elektronik telah diberlakukan untuk Form D, E, AK, IJEPA (menurut FAQ resmi DJBC: https://www.beacukai.go.id/faq-fasilitas-fta).
- Untuk **impor**, importir mencantumkan **kode fasilitas preferensi + nomor & tanggal SKA** pada PIB dan menyimpan salinan SKA.

**Sumber:**
- FAQ Fasilitas FTA DJBC: https://www.beacukai.go.id/faq-fasilitas-fta
- e-SKA Kemendag: https://ska.kemendag.go.id ; https://ftasupportcenter.kemendag.go.id
- IC-CEPA: https://ftasupportcenter.kemendag.go.id/ic-cepa
- IEU-CEPA: https://jatim.antaranews.com/berita/946133/indonesia-eu-cepa-agreement-concluded-after-10-years ; laporan ratifikasi 2026 (RRI/Kumparan)
- GSP: https://www.cbp.gov/trade/priority-issues/trade-agreements/special-trade-legislation/generalized-system-preferences

---

## 10. CEISA 4.0: API untuk PPJK/PPK

1. **Open API resmi tersedia.** DJBC menerbitkan dokumen "CEISA 4.0 Open API — Seamless Integration" sebagai enabler integrasi sistem (host-to-host) untuk dokumen kepabeanan (PIB/PEB), menggantikan pola webservice PIB 3.0. Dokumen resmi: https://onewebfile.beacukai.go.id/cdn/download/file/685a4669846e515ab1d90c72
2. **Tiga peran pengguna API** (materi asistensi DJBC): (a) Pengguna API sekaligus Pengguna Jasa (importir/PPJK yang kirim dokumen sendiri), (b) Pengguna Jasa yang mengakses platform penyedia API (mis. PPJK memakai vendor), (c) Penyedia API (vendor integrator).
3. **Mekanisme otorisasi:** akun **Portal CEISA** per badan usaha; importir memberi **otorisasi kuasa kepada PPJK di CEISA 4.0**; PPJK merekam PIB atas nama importir; koneksi mesin-ke-mesin memerlukan registrasi/kesepakatan teknis dengan DJBC (kredensial + endpoint per layanan). Detail endpoint tidak sepenuhnya publik — perlu akun CEISA & akses dokumentasi resmi `[PERLU VERIFIKASI]`.
4. **Integrasi INSW:** SPT PIB elektronik terbit dari PIB yang direkam di CEISA; INSW (https://insw.go.id) menjadi pintu perizinan/lartas (SPT, PIU); CEISA menangani proses kepabeanan setelahnya. User manual resmi: https://usermanualceisa40.gitbook.io/portal-ceisa40 dan https://linktr.ee/um.dokkepabeanan.ceisa40.
5. Praktis untuk sistem web freight forwarder: mode ganda — (a) input manual + kirim via portal, (b) host-to-host API bila PPJK sudah disetujui sebagai Pengguna API.

---

## 11. Lartas Impor: API-U vs API-P

1. **API-U (Umum):** importir yang barangnya **diperdagangkan** (trader/reseller). **API-P (Produsen):** importir industri yang barangnya **bahan baku/penunjang proses produksi sendiri**, tidak untuk diperdagangkan.
2. Sejak UU Cipta Kerja, **NIB berlaku sebagai API** (terbit via OSS-RBA); jenis API mengikuti KBLI. Perubahan **API-U → API-P dapat dilakukan, sebaliknya tidak** (Permendag 36/2023 Pasal 2 ayat (13), dikutip sumber sekunder).
3. **Dampak pada PIB:** jenis API wajib dicantumkan di PIB; kesalahan jenis (mis. API-P dipakai untuk barang dagangan) berisiko masalah kepastian hukum/pengawasan; sejumlah lartas/izin khusus hanya cocok untuk salah satu jenis; **dampak tarif PPh 22**: kategori umum dengan API 2,5% vs tanpa API 7,5% (Bagian 2).
4. **Pengecualian kewajiban API** untuk impor tertentu (penelitian/pengembangan, promosi, film, bantuan/hibah) — PMK 36/PMK.04/2021.

**Sumber:** https://siplawfirm.id/id/publikasi/angka-pengenal-importir-jenis-api-dampak-bisnis ; https://golaw.id/blog/api-u-atau-api-p-begini-cara-menentukan-izin-impor-yang-sesuai-kbli ; PMK 36/PMK.04/2021 (peraturan.beacukai.go.id)

---

## 12. BTKI (Kitab Tarif Kepabeanan Indonesia)

1. **BTKI 2022 berlaku sejak 1 April 2022** — **PMK 26/PMK.010/2022** (Sistem Klasifikasi Barang dan Pembebanan Tarif BM atas Barang Impor), mengadopsi **HS 2022 WCO**, total **11.414 pos tarif** (naik dari 10.813 pada BTKI 2017).
2. **Revisi:** PMK 26/2022 telah diubah oleh **PMK 62 Tahun 2025** (tercatat di JDIH per 15 September 2025) — perubahan pos tarif tertentu. `[PERLU VERIFIKASI]` isi & cakupan PMK 62/2025.
3. **Tidak ada "BTKI 2025" atau "BTKI 2026"** sebagai kitab baru — yang berlaku tetap BTKI 2022 + perubahan. Tidak ditemukan pengumuman kitab BTKI baru (revisi besar biasanya mengikuti siklus HS WCO berikutnya: HS 2028).

**Sumber:** https://www.beacukai.go.id/btki-dan-tarif ; https://jdih.kemenkeu.go.id/dok/26-pmk-010-2022

---

## 13. UU PDP 27/2022 & PP 71/2019 (aplikasi penyimpan data konsignee)

1. **Dasar pemrosesan — Pasal 20 UU PDP:** pemrosesan data pribadi hanya sah jika berdasarkan salah satu dari: (a) persetujuan subjek data; (b) kewajiban pemenuhan perjanjian; (c) pemenuhan kewajiban hukum; (d) kepentingan vital; (e) pelaksanaan tugas publik; (f) kepentingan yang sah. Untuk aplikasi forwarder yang menyimpan data konsignee (nama, alamat, telepon): gunakan (b) pelaksanaan kontrak layanan dan/atau (a) persetujuan; dasar "kepentingan sah" untuk keperluan operasional terbatas.
2. **Lokasi pemrosesan — Pasal 55 UU PDP:** pengendali/prosesor wajib memproses data pribadi **dalam yurisdiksi Indonesia**, kecuali (Pasal 56): (a) negara tujuan punya tingkat pelindungan setara/lebih tinggi; (b) ada pengikatan diri/perjanjian yang memadai dan mengikat; (c) ada persetujuan terdokumentasi dari subjek data. → **Cloud/on-premise server berlokasi Indonesia adalah opsi paling aman**; jika memakai CDN/SaaS luar negeri, dokumentasikan salah satu pengecualian Pasal 56.
3. **PP 71/2019 (PSTE):** Penyelenggara Sistem Elektronik wajib melakukan pemrosesan data di wilayah NKRI (Pasal 20, dengan pengecualian pada ayat berikutnya `[PERLU VERIFIKASI redaksi ayat]`), wajib menyelenggarakan sistem yang andal & aman, melindungi data pribadi, dan memberi akses subjek data ke datanya. Kewajiban ini berlaku juga bagi agen elektronik mutatis mutandis.
4. **Kewajiban pelanggaran — Pasal 46 UU PDP:** kegagalan pelindungan data pribadi wajib dilaporkan tertulis **3×24 jam** kepada subjek data dan lembaga yang berwenang.
5. **Status:** UU PDP berlaku penuh sejak Oktober 2024 (akhir masa transisi 2 tahun); lembaga pengawas PDP mulai berfungsi bertahap `[PERLU VERIFIKASI]` status kelembagaan per Nov 2026.

**Sumber:**
- UU 27/2022: https://peraturan.bpk.go.id (UU 27/2022); ringkasan https://yaplegal.id/panduan/pdp
- PP 71/2019: https://peraturan.bpk.go.id/Details/122030/pp-no-71-tahun-2019 ; https://jdih.komdigi.go.id/produk_hukum/view/id/695

---

## Lampiran A — Implikasi ke desain sistem (kalkulator PIB)

| Asumi lama spesifikasi | Fakta regulasi | Perbaikan desain |
|------------------------|----------------|------------------|
| "PPN flat 11%" | Tarif formal 12%; barang umum memakai DPP nilai lain 11/12 (efektif 11%); barang mewah 12% penuh (PMK 11/2025 jo. PMK 53/2025, sebelumnya PMK 131/2024) | Simpan tarif formal 12% + flag `barang_mewah`; tampilkan DPP = Nilai Impor × 11/12 untuk non-mewah; dasar = Nilai Impor (CIF+BM+pungutan lain), bukan CIF |
| "PPh 22 flat 2,5%/7,5%" | PMK 51/2025: tarif per lampiran — 10% (Lampiran A), 7,5% (Lampiran B & tanpa API), 0,5% (kedelai/gandum/terigu), 0,25% (emas batangan), 2,5% umum dengan API; dasar = Nilai Impor | Engine tarif PPh 22 berbasis kode HS → kategori lampiran; input "punya API / tidak"; jangan hardcode 2,5% |
| "Pengecualian impor ≤ USD 50" | Rezim lama: berbasis **nilai FOB** ≤ USD 50 (PMK 34/2017 jo 41/2022). PMK 51/2025: tidak ditemukan dalam fulltext `[PERLU VERIFIKASI]`; kiriman ≤ USD 3 → PPh 22 tidak dipungut (PMK 96/2023) | Jangan aktifkan threshold USD 50 untuk impor umum sampai PMK 51/2025 diverifikasi dari PDF resmi; threshold USD 3 hanya untuk jalur kiriman |
| "Demurrage hari ke-0 = discharge" | Praktik komersial (bukan regulasi): free time umumnya mulai hari **discharge/container available** di terminal; hari kerja vs kalender & jumlah hari berbeda per shipping line/terminal (Maersk 4–5 hari kerja, SITC 7–10 hari kalender, dll.) | Konfigurasi free time per line/terminal (hari kerja vs kalender), epoch = tanggal discharge; jangan satu angka global |
| Kurs pajak: "Rabu diterbitkan, Senin berlaku" | KMK berlaku **Rabu–Selasa** (FAQ resmi fiskal.kemenkeu.go.id, contoh KMK 51/MK/EF.2/2026: 28 Okt–3 Nov 2026); ada **API resmi gratis** (Layanan API Nilai Kurs) | Scheduler perubahan KMK hari Rabu; integrasi API kurs + fallback download Excel |
| Nilai pabean = PMK 203/2017 | Dasar yang benar **PMK 144/PMK.04/2022** (PMK 203/2017 = barang penumpang); asuransi 0,5% × FOB bila tak diketahui | Rumus: Nilai Pabean = (FOB + freight + asuransi) × kurs; asuransi default 0,5% × FOB |
| BM < Rp25.000 tidak dipungut (PP 34/2017) | PP 34/2017 adalah PPh final sewa tanah/bangunan — bukan dasar BM. Pembulatan: KMK 584/KMK.05/1996 (ribuan ke atas). Rp25.000 dari rezim PP 84/2001 `[PERLU VERIFIKASI]` | Implementasikan pembulatan ribuan ke atas; threshold Rp25.000 jadikan konfigurasi opsional dengan disclaimer |
| Retensi "10 tahun" generik | Kepabeanan 5 tahun (UU Kepabeanan Ps. 117); pajak 10 tahun (UU KUP Ps. 28 ayat (1) huruf c) | Kebijakan retensi data & audit trail: 10 tahun |
| PMK 37/2022 = PPh 22 impor | PMK 37/PMK.03/2022 = PPh 22 domestik; impor = PMK 51/2025 | Perbaiki referensi hukum di UI/dokumen |

---

## Lampiran B — Daftar tautan sumber utama

Resmi:
- JDIH Kemenkeu: https://jdih.kemenkeu.go.id
- Peraturan BPK: https://peraturan.bpk.go.id
- Kurs pajak BKF: https://fiskal.kemenkeu.go.id/informasi-publik/kurs-pajak | API: https://fiskal.kemenkeu.go.id/layanan/layanan-api-nilai-kurs
- DJP: https://pajak.go.id
- DJBC: https://www.beacukai.go.id | direktori peraturan: https://peraturan.beacukai.go.id
- INSW: https://insw.go.id | e-SKA Kemendag: https://ska.kemendag.go.id
- FTA Support Center Kemendag: https://ftasupportcenter.kemendag.go.id

Sekunder (konsultan pajak):
- ORTAX datacenter: https://datacenter.ortax.org
- DDTC News: https://news.ddtc.co.id
- MUC Consulting: https://muc.co.id

---

*Disusun otomatis oleh riset compliance (ZCode), 1 November 2026. Seluruh item berlabel [PERLU VERIFIKASI]/[BELUM DIVERIFIKASI] harus dicek ulang ke PDF resmi JDIH sebelum dipakai sebagai dasar keputusan hukum.*
