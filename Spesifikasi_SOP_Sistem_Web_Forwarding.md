# SPESIFIKASI SISTEM: WEB-BASED FREIGHT FORWARDING MANAGEMENT SYSTEM
## PPJK — IMPOR LAUT FCL, KALKULATOR PIB v2, DEMURRAGE/DETENTION ENGINE, BILLING & KEPATUHAN

**Status:** SPESIFIKASI FINAL v2 — menggantikan seluruh isi versi sebelumnya (arsip: `Spesifikasi_v1_arsip.md`).
**Revisi v2.1 (1 Nov 2026):** pengayaan hasil review draft spesifikasi independen — migrasi & cutover, koreksi milestone, uang muka & settlement, notifikasi, mekanika invoice, persyaratan stack, PV-16..19. Sumber keputusan tetap Q1–Q34; pengayaan ditandai *(v2.1)*.
**Tanggal:** 1 November 2026.
**Dasar penyusunan:** 34 keputusan terkunci hasil sesi grill-with-docs (indeks Q1–Q34, Lampiran F), 9 ADR (`docs/adr/`, Lampiran B), GLOSSARY.md, dan riset regulasi per 1 Nov 2026 (`docs/research/regulasi-kepabeanan-dan-pajak.md`).

**Kaidah membaca dokumen:**
1. Istilah mengikuti `GLOSSARY.md` secara mutlak (PPJK, Importir, Job, Kontainer, Milestone, Nilai Pabean, Nilai Impor, Seri Barang, Kurs Pajak, Kurs Internal, Snapshot PIB, dst.).
2. Angka dan aturan hukum bertanda **[PV-n]** berstatus *Perlu Verifikasi* — WAJIB dicek ke PDF resmi JDIH sebelum dipakai produksi (Lampiran C); gerbang blokirnya diatur ADR 0008.
3. Kode dalam dokumen adalah **spesifikasi perilaku engine**, bukan implementasi final.

---

## 1. KONTEKS BISNIS & KEWAJIBAN KEPATUHAN

### 1.1 Posisi hukum (Q1)
Pemilik sistem adalah **PPJK berizin** (Pengusaha Pengurusan Jasa Kepabeanan) yang mengurus impor atas nama importir klien. PPJK bertanggung jawab atas kebenaran dan kelengkapan pemberitahuan pabean yang direkam (UU 10/1995 Ps. 29 jo. UU 17/2006; registrasi via Portal CEISA; wajib Ahli Kepabeanan — PMK 219/PMK.04/2019). Konsekuensi desain: setiap angka yang menjadi dasar pemberitahuan wajib punya jejak pembuat, penyetuju, dan waktu (§2.5, §3.7).

### 1.2 Cakupan v1 (Q2, Q10)
- **Di dalam scope:** impor laut **FCL** end-to-end (dokumen asal → discharge → DO → PIB/SPPB → gate-out → trucking → gate-in empty → tagihan → close) + **trucking vendor pihak ketiga**.
- **Di luar scope v1 (dinyatakan eksplisit):** LCL/konsolidasi, ekspor, udara, pergudangan, klaim refund MFN→FTA, invoice USD. Fase berikutnya di §9.

### 1.3 Skala target (Q6)
100–300 job/bulan, 5–15 user internal, 10–50 importir aktif. ±10–15 entri milestone/hari — input manual per web mobile-friendly adalah cukup untuk v1 (Q12).

### 1.4 Arsitektur data (Q3, ADR 0001)
Internal tool untuk satu perusahaan, tetapi **seluruh tabel data bisnis membawa `company_id` sejak migrasi pertama** dengan isolasi ditegakkan di layer query. Master referensi publik (tarif, HS, kurs) boleh global dengan cache per company. Tidak ada tabel data bisnis tanpa `company_id`.

### 1.5 Kepatuhan rekaman (Q5)
- **Audit trail** "siapa mengubah apa, kapan, alasannya" untuk: setiap angka yang masuk PIB, milestone, override (FTA, blokir, alokasi, pengecualian), invoice, dan reopen close.
- **Arsip digital bersifat deactivate-only**: dokumen transaksi + lampiran (Invoice, PL, B/L, Form FTA, bukti milestone) tidak dapat dihapus, hanya dinonaktifkan dengan alasan.
- **Retensi 10 tahun** (UU KUP Ps. 28 ayat (1) huruf c — menutup kewajiban kepabeanan 5 tahun, UU Kepabeanan Ps. 117). Arsip harus dapat diekspor sebelum penghapusan. **Arsip ≠ backup** (§8.2).

### 1.6 Pelindungan data pribadi (Q34)
- Data konsignee/importir diproses dengan dasar **pelaksanaan kontrak layanan** (UU PDP Ps. 20 huruf b) dan/atau persetujuan.
- Pemrosesan **di wilayah Indonesia** (UU PDP Ps. 55–56; PP 71/2019 Ps. 20). Keluar yurisdiksi hanya dengan pengecualian terdokumentasi — redaksi pengecualian **tidak ditulis spesifik sampai diverifikasi [PV-11]**.
- **Insiden kebocoran wajib dilaporkan 3×24 jam** (UU PDP Ps. 46) — runbook keamanan masuk v1 (§8.4).

### 1.7 Titik awal data & cutover (Q7; penajaman v2.1)
Greenfield dari Excel + WhatsApp. Migrasi hanya **master data**: importir, Tarif Line, HS favorit, master trucking, checklist override — **plus shipping line, pelabuhan/depot, gudang tujuan, jenis kontainer** (tanpa itu, Ops mengetik ulang di tiap Job).
- Pembersihan pra-migrasi: dedup importir **berdasarkan NPWP, bukan nama** — nama sama dengan ejaan berbeda adalah sumber duplikat paling umum.
- Job yang berjalan saat go-live diinput sebagai job baru, dengan ketentuan:
  - Milestone yang sudah terjadi diinput **backdate dengan tanggal kejadian asli** (bukan tanggal input) dengan alasan tercatat — tanpa ini hitungan demurrage salah.
  - Job menyimpan field `referensi_excel_lama` dan `tanggal_cutover`.
  - Seluruh master yang dipakai job berjalan harus sudah ada di hasil migrasi.

### 1.8 Koreksi atas Spesifikasi v1 (jangan terbawa ke build) *(v2.1)*

| Kesalahan/asumsi v1 | Koreksi di dokumen ini |
|---|---|
| Tabel Shipment menyimpan satu Container Number | Job 1..N Kontainer sebagai entitas (§2.1) |
| "NDR (Nilai Dasar Rumah Tangga / NDPBM)" | NDPBM = **Nilai Dasar Pabean Menteri Keuangan**; singkatan "NDR" tidak dipakai di mana pun (§3.5) |
| PPN flat 11%; PPh 22 flat 2,5%/7,5%; total tanpa pungutan lain; dasar PMK 203/2017 | Rumus v2 (§3.2): DPP 11/12, PPh 22 per lampiran PMK 51/2025, PPnBM, dasar Nilai Impor, PMK 144/2022 |
| Angka tarif contoh USD 35/65/110 di spesifikasi & kode | Dihapus total; tarif hanya dari Tarif Line berversi (§4.3) |
| "Auto-Invoicing berdasarkan Real-cost" | Draf invoice otomatis dari ledger, tapi terkirim hanya setelah approve manual (§6.4) |
| Email blast alarm ke Importir | v1 notifikasi internal: PIC Job + Finance; ke Importir lewat fase 2/WA (§2.6) |
| Status sebagai kolom yang diubah manual (tersirat) | Status = proyeksi milestone (§2.3) |
| "SPT PIB" dipakai untuk draf internal | PIB = draf; SPT PIB = sudah difile dengan nomor aju (Glossary) |
| Multi-currency "tabel konversi harian terpusat" tunggal | Dua kurs terpisah: Kurs Pajak vs Kurs Internal (§3.5, §6.6) |

---

## 2. ARSITEKTUR ALUR OPERASIONAL

### 2.1 Entitas inti (Q10)
- **Job = 1 Importir + 1 B/L**, dengan **1..N Kontainer**. Dua B/L untuk importir yang sama = dua job terpisah; B/L yang dipecah menjadi 2 PIB dibuat sebagai **2 job yang saling ter-link**.
- **Kontainer** (entitas first-class, di bawah Job): nomor, ukuran (20'/40'/40'HC), tipe, nomor seal, berat.
- **Milestone fisik per Kontainer** (discharge, gate-out full, terkirim, gate-in empty); **DO dan seluruh proses pabean & tagihan di level Job**. Demurrage/detention dihitung per Kontainer, ditotalkan di level Job.
- **Kalkulator PIB & Snapshot bekerja per Seri Barang** (baris barang dengan HS-nya sendiri) — bukan per Job.

### 2.2 Dua cabang alur (Q15)
```
CABANG PABEAN   : PIB disiapkan → (approval + snapshot) → difile menjadi SPT PIB → SPPB
CABANG LOGISTIK : Discharge → DO terbit → Gate-Out Full → Terkirim (POD) → Gate-In Empty
```
- Milestone **dicatat independen per cabang**; tampilan linear hanyalah proyeksi.
- **Validasi kronologis dalam cabang saja, dan sistem MENOLAK (bukan memperingatkan):** SPPB ≥ PIB filed; gate-in empty ≥ gate-out full; gate-out full ≥ DO terbit; DO ≥ discharge.
- DO yang terbit lebih awal **tidak membuat job mundur** — status tetap di tahap cabang pabean, dengan penanda terpisah "DO sudah terbit".

### 2.3 Status Progres = proyeksi milestone (Q11, ADR 0002)
- Status **dihitung dari milestone**, bukan kolom manual. Edit status manual **tidak ada di sistem**.
- Daftar status (dikunci): `Draft → Dokumen Lengkap → {pabean: PIB Filed → SPPB} ∥ {logistik: DO Terbit → Gate-Out Sebagian/Selesai → Terkirim → Gate-In Empty Sebagian/Selesai} → Ditagih → Lunas → Closed`.
- Di-cache untuk dashboard; **dihitung ulang setiap perubahan milestone**.
- **Problem Flag terpisah** dari status: Hold, Dispute, Dokumen Bermasalah — job bisa berprogres sambil di-hold.
- **Dokumen Lengkap** dihitung dari checklist fase Pra-Filing (§2.4), bukan penilaian subjektif.
- **Syarat Close:** tagihan lunas **dan** semua kontainer gate-in empty **dan** arsip dokumen seluruh fase lengkap **dan** tidak ada selisih demurrage/detention yang belum diselesaikan **dan** settlement uang muka per Job sama dengan nol (§6.3).
- **Reopen close** hanya oleh Admin/Finance dengan alasan; pembukaan tercatat di audit trail.

### 2.4 Checklist dokumen berfase (Q21)
| Fase | Item default |
|---|---|
| **Pra-Filing** | Invoice, Packing List, B/L, Form FTA (bila klaim) |
| **Pasca-Filing** | DO, SPPB |
| **Pasca-Delivery** | Bukti gate-out, bukti gate-in, POD |

- Status tiap item: `Belum ada / Diupload / Diverifikasi`. "Dokumen Lengkap" (untuk progres PIB) = semua item Pra-Filing yang berlaku berstatus **Diverifikasi**. Form FTA berstatus *tidak berlaku* bila klaim FTA = tidak.
- Override daftar per **Importir** dikonfigurasi di master dengan alasan, penyetuju, tanggal berlaku. Pengecualian per Job hanya dengan alasan tercatat + penyetuju.
- **POD** = bukti terkirim ke gudang importir (mis. Surat Jalan bertanda tangan) dan **ter-link ke Surat Jalan** terkait — bukan file lepas.

### 2.5 RBAC & maker-checker (Q23, ADR 0006)
**6 peran** (satu user boleh memegang lebih dari satu; izin dihitung per peran via **matriks peran×fitur eksplisit**):

| Peran | Cakupan |
|---|---|
| **Admin** | Master data, user, checklist master, konfigurasi. **Bukan superuser** — kerja operasional butuh peran eksplisit. |
| **CS** | Entri job & Dokumen Asal. |
| **Customs Specialist** | Kalkulasi, approve & Snapshot PIB, override FTA, flag pengecualian. |
| **Ops** | Assignment job, verifikasi milestone, vendor & Surat Jalan. |
| **Field** | Input milestone + bukti **hanya job yang ditugaskan** (Job, Kontainer, Milestone, Surat Jalan itu saja; tanpa invoice/kurs/titipan). |
| **Finance** | Kurs Internal, invoice, pembayaran & alokasi, deposit, titipan dana, reopen close. |

**Maker-checker ditegakkan per user ID**: pencipta ≠ penyetuju, berlaku untuk approve Snapshot PIB, override FTA, approve invoice, reopen close, dan update tarif — walau satu orang memegang beberapa peran. Ops tidak boleh memverifikasi inputannya sendiri. Persetujuan kedua mengikat; persetujuan pertama tercatat sebagai review.

### 2.6 Notifikasi (Q8; penajaman v2.1)
v1: **email + badge di dashboard + Telegram** (satu bot; target chat per jenis alert dikonfigurasi di master). WhatsApp Business API = fase 2.
- **Pemicu alarm dihitung per Kontainer, bukan per Job:** H-2 sebelum Free Time habis, H-0 hari terakhir, H+1 setelah lewat.
- **Penerima v1: PIC Job + Finance — bukan blast ke Importir** (pemberitahuan ke Importir lewat fase 2/WA). Isi email: nomor Job, nomor Kontainer, last free date, estimasi biaya saat ini, dan link langsung ke Job.
- **Badge dashboard:** hitungan kontainer per tingkat (kuning = H-2/H-0; merah = sudah lewat Free Time).
- **Log notifikasi:** semua yang terkirim tercatat (kapan, ke siapa, kontainer mana) — supaya tidak ada yang mengklaim "tidak diberitahu".

### 2.7 Input milestone (Q12)
- Web mobile-friendly; tiap milestone wajib: tanggal/jam kejadian, penginput, dan **bukti untuk milestone kunci** — SPPB & DO wajib nomor + foto/scan; gate-out/gate-in foto kontainer bila memungkinkan.
- **Entri batch** satu aksi untuk beberapa kontainer (tersimpan tetap per kontainer).
- **Backdate** diizinkan dengan alasan tercatat; form dapat menyimpan **draft** (sinyal lapangan sering tanpa sinyal).
- Field hanya untuk job yang ditugaskan; Ops memverifikasi.
- **Milestone tidak pernah dihapus** *(v2.1)*. Koreksi dilakukan dengan **Milestone pengganti yang merujuk ke Milestone lama**; keduanya tersimpan di audit trail.

---

## 3. KALKULATOR PIB v2 & SNAPSHOT PIB

### 3.1 Peran (Q4)
Kalkulator PIB adalah **estimator internal** — bukan dokumen resmi dan bukan sumber tagihan pungutan (ADR 0004). Struktur datanya **identik dengan isian PIB CEISA** (header + detail/seri barang) sehingga fase 2 tidak butuh migrasi konsep. Seluruh referensi hukum yang tampil di UI memakai dasar yang benar: **PMK 144/PMK.04/2022** (nilai pabean — *bukan* PMK 203/2017 yang itu barang penumpang), **PMK 51/2025** (PPh 22 impor — *bukan* PMK 37/2022 yang itu PPh 22 domestik), **PMK 11/2025 jo. PMK 53/2025** (DPP 11/12).

### 3.2 Rumus engine v2 (Q28, Q30, Q32)
```python
# INPUT PER SERI BARANG: HS (BTKI 2022), FOB/Insurance/Freight per mata uang,
#   flag barang_mewah (PER SERI BARANG — satu Job bisa campuran),
#   status_API, klaim FTA (kode fasilitas + nomor & tanggal SKA),
#   flag pengecualian PPh 22 Pasal 4 (manual per baris HS, menyebut pungutan
#   mana yang dibebaskan, disetujui Customs Specialist, dokumen diupload)
# KURS PAJAK per mata uang: KMK mingguan, validitas Rabu–Selasa (§3.5)

# 1. Nilai Pabean (PMK 144/PMK.04/2022)
insurance = input_asuransi \
            or 0.5% × FOB          # fallback bila asuransi tak diketahui
            # varian 0.5% × CFR bila kondisi CFR — BASIS YANG DIPAKAI WAJIB DICATAT DI SNAPSHOT
            # asuransi yang ditutup di dalam negeri dianggap 0 (v2.1)
CIF_valas(u) = FOB(u) + Insurance(u) + Freight(u)   # per mata uang u
CIF_Rupiah   = Σ CIF_valas(u) × Kurs_Pajak(u)       # DISIMPAN PRESISI PENUH (Lampiran E)

# 2. Bea Masuk
Tarif_BM = Tarif_FTA(HS, skema)  jika klaim FTA valid
         = Tarif_MFN(HS)         selainnya
BM = bulatkan_ke_atas_ribuan(CIF_Rupiah × Tarif_BM)     # per seri barang

# 3. Nilai Impor = CIF + BM + pungutan lainnya  → dasar PPN, PPh 22, PPnBM
Nilai_Impor = CIF_Rupiah + BM + Pungutan_Lain

# 4. PPN Impor — tarif formal 12% dari master berversi (bukan hardcode)
DPP_PPN = Nilai_Impor × (11/12)   jika bukan barang_mewah
        = Nilai_Impor             jika barang_mewah
PPN = bulatkan_ke_atas_ribuan(Tarif_PPN_formal × DPP_PPN)   # per seri barang

# 5. PPh 22 Impor (PMK 51/2025) — URUTAN PENGECEKAN DIKUNCI:
#    (1) Lampiran A 10% → (2) Lampiran B 7,5% →
#    (3) 0,5% kedelai/gandum/terigu DENGAN API → (4) 0,25% emas batangan →
#    (5) umum 2,5% dengan API / 7,5% tanpa API →
#    (6) kasus khusus barang lelang: 7,5% dari harga jual lelang
PPh22 = bulatkan_ke_atas_ribuan(Nilai_Impor × Tarif_PPh22)  # per seri barang

# 6. PPnBM (PP 73/2019 + PMK 96/2021) — dari Nilai Impor; umumnya 0 utk kargo umum
#    Engine MENDUKUNG aturan ambang Nilai Impor; data ambang [PV-6] NONAKTIF sampai terverifikasi
PPnBM = bulatkan_ke_atas_ribuan(Nilai_Impor × Tarif_PPnBM(HS, aturan_ambang))

Total_Pungutan = BM + PPN + PPh22 + PPnBM + Pungutan_Lain
# (koreksi Ronda 5: pungutan lain — mis. BMAD/BMTP — TIDAK hilang dari total)
```

**Kewajiban tampilan (v2.1):** Kalkulator wajib menampilkan per Seri Barang — Nilai Impor, DPP, tarif formal, dan hasil; bukan hanya hasil akhir. Untuk barang umum tampilkan secara eksplisit: *"DPP Nilai Lain = Nilai Impor × 11/12"*.

### 3.3 Ambang & kasus tepi (Q30)
1. **Threshold "USD 50" TIDAK diimplementasikan** — tidak ditemukan di PMK 51/2025. Bila terkonfirmasi masih ada setelah cek PDF [PV-2], ditambah sebagai konfigurasi **berbasis FOB, default nonaktif**, dengan penjelasan pungutan mana yang dibebaskan.
2. **Rezim kiriman ≤ USD 3** (PMK 96/2023) di luar scope (FCL). Sistem **menolak/memperingatkan keras** bila rezim kiriman dipakai untuk Job FCL (aturannya berbeda: BM dibebaskan, PPN tetap, PPh 22 tidak — tidak boleh tercampur kalkulator FCL).
3. **BM < Rp25.000 tidak dipungut** = konfigurasi opsional **default nonaktif** dengan disclaimer; sebelum diaktifkan wajib verifikasi penerusnya (rezim PP 84/2001 [PV-5]) dan apakah ambangnya per seri barang atau per PIB.
4. **Pengecualian PPh 22 Pasal 4** (buku, hibah, hulu migas, dsb.) = **flag manual per baris HS**, menyebut pungutan mana yang dibebaskan (sebagian pengecualian juga membebaskan BM/PPN), disetujui Customs Specialist, dokumen pendukung diupload, alasan tampil di Snapshot PIB. Bukan logika otomatis.

### 3.4 Pembulatan dua layer (Q32)
- **Layer PIB (statutori):** pembulatan **ke atas ribuan penuh per komponen, per seri barang** (KMK 584/KMK.05/1996; PMK 190/2022 Ps. 22 ayat (4)).
- **Layer invoice (internal):** pembulatan **ke rupiah penuh per baris**; total invoice = jumlah baris yang sudah dibulatkan (Q24).
- Keduanya hidup berdampingan dengan label UI berbeda: *"Pembulatan PIB — statutori, ke atas ribuan"* vs *"Pembulatan Invoice — internal, rupiah penuh"*.
- Snapshot menyimpan **angka pra-pembulatan + hasil pembulatan** tiap komponen per seri barang, beserta nomor KMK dan versi tarif yang dipakai. CIF Rupiah disimpan presisi penuh sampai aturan pembulatannya dikonfirmasi ke CEISA (Lampiran E).

### 3.5 Kurs Pajak (Q24, riset §4)
- Kurs Pajak = kurs Kemenkeu, dikenal di PIB sebagai **NDPBM — Nilai Dasar Pabean Menteri Keuangan** *(koreksi v2.1; singkatan "NDR / Nilai Dasar Rumah Tangga" di v1 salah)*. Verifikasi redaksi resminya saat cek PV-3.
- KMK berlaku **Rabu–Selasa** (bukan "Rabu terbit Senin berlaku" seperti premis lama); saat libur panjang bisa 2 pekan.
- Diambil otomatis via **API resmi gratis Kemenkeu** (Layanan API Nilai Kurs) — endpoint teknis [PV-14]; fallback download Excel; fallback manual dengan sumber dicatat.
- Disimpan per mata uang dengan **nomor KMK + tanggal validitas + sumber**; tabel terpisah dari Kurs Internal; dipakai per mata uang di rumus §3.2.

### 3.6 Empat master tarif ber-versi (Q29, ADR 0008)
| Master | Isi | Dasar |
|---|---|---|
| (a) MFN per HS | Tarif bea masuk normal | BTKI 2022 (PMK 26/PMK.010/2022) + PMK 62/2025 [PV-7] |
| (b) Preferensi per skema per HS | Tarif FTA (Form E, D, AK, AI, ANZ, IJEPA, IA-CEPA, IC-CEPA, RCEP) | PMK 88/2023 [PV-9]; IEU-CEPA **belum berlaku** (target 2027) |
| (c) Mapping HS→lampiran PPh 22 | Lampiran A/B/khusus | PMK 51/2025 — **seed = kerangka**; daftar HS lengkap dibangun dari PDF lampiran resmi [PV-8] |
| (d) Barang mewah | **Dua sub-daftar terpisah**: PPN-mewah & PPnBM (tidak identik) | PMK 131/2024; PP 73/2019 + PMK 96/2021 [PV-6] |

Semua effective-dated (pola sama dengan Tarif Line §4.3). Update: diajukan dengan **alasan + dasar hukum tercatat**, **tidak boleh disetujui pembuatnya sendiri** (maker-checker). Tiap item membawa status **Terverifikasi / Perlu Verifikasi**: draf estimasi boleh memakai item Perlu Verifikasi dengan label peringatan; **Snapshot PIB untuk filing MEMBLOKIR item tersebut**.

### 3.7 Snapshot PIB (Q20)
- **Isi:** FOB, insurance (+basis fallback), freight, CIF valas, Kurs Pajak + tanggal/sumber/nomor KMK, HS, tarif MFN vs FTA, BM, Nilai Impor, DPP & PPN, PPh 22, PPnBM, pungutan lain, total, **versi Kalkulator**, nama & waktu approval Customs Specialist.
- **Immutable.** Revisi = versi baru (v1, v2, v3) dengan alasan + penyetuju; versi lama tetap terlihat dengan perbandingan perubahan. Draft yang belum disetujui tersimpan, tidak ditimpa.
- Kurs Pajak berganti antara draft dan filing → draft ditandai **stale**, wajib approve ulang; sistem tidak boleh diam-diam memakai kurs baru.
- Checklist konfirmasi ke importir (v1 via WA/email di luar sistem): field tanggal konfirmasi, channel, oleh siapa.
- Setelah filing: **nomor aju SPT PIB, billing code, NTPN ditempelkan ke versi snapshot yang difile** — bukan ke Job secara umum.
- Lembar Kerja PIB (§3.9) hanya boleh dari snapshot yang **sudah disetujui**.

### 3.8 Validasi FTA — penjaga gerbang (Q19)
- Form FTA diupload dengan metadata: nomor, tanggal terbit, negara penerbit/asal, masa berlaku, HS di Form, pelabuhan muat — **terikat ke baris HS di PIB** (satu Job bisa beberapa HS).
- Validasi HS memakai **prefix 6 digit** (Form ~6 digit, PIB 8 digit) — pencocokan string penuh akan menolak klaim yang benar.
- Validasi kedua: negara asal/pelabuhan muat Form ↔ data PIB.
- Mismatch (HS, negara/pelabuhan, **atau tanggal filing lewat masa berlaku Form**) = **peringatan keras**: filing tidak bisa lanjut kecuali Customs Specialist melakukan override dengan alasan tercatat + lampiran (masuk audit trail).
- Form belum ada saat mau filing → **decision log** (siapa, kapan, alasan, risiko masa berlaku); Job ditandai "FTA tidak diklaim, MFN" dan **tidak boleh diam-diam diubah ke FTA di versi PIB yang sama**.
- Klaim ulang MFN→FTA (refund) = proses manual di luar sistem v1; sistem hanya mencatat nomor referensi refund eksternal.

### 3.9 Lembar Kerja PIB (Q26)
- Sumber: **Snapshot PIB yang sudah disetujui** (bukan draf kalkulator); nomor versi snapshot dicetak; label tegas **"LEMBAR KERJA, BUKAN DOKUMEN RESMI"**.
- Format: PDF + tampilan web, **urutan field mengikuti isian CEISA 4.0** sebagai **template ber-versi** — layar CEISA berubah → template naik versi; lembar kerja lama tetap terlacak ke versi template-nya. Tidak di-hardcode sebagai satu-satunya urutan di kode.
- PDF dan web menampilkan angka yang sama dari snapshot yang sama.
- **Memo Tagihan** dan **Surat Jalan** dibuat dari data Job/Kontainer/ledger yang sama — tidak ada ketik ulang nomor kontainer, driver, atau nopol.

### 3.10 Fase 2 — integrasi CEISA 4.0
Open API resmi DJBC tersedia (host-to-host); peran Pengguna API/Pengguna Jasa/Penyedia API; butuh akun Portal CEISA + **otorisasi kuasa importir→PPJK**; SPT PIB terbit via INSW. Tidak dibutuhkan di v1; struktur data sudah mengikuti isian PIB (§3.1).

---

## 4. ENGINE DEMURRAGE & DETENTION

### 4.1 Dua pelacak terpisah per Kontainer (Q9, Q10)
```
DEMURRAGE : Hari 0 = tanggal Discharge. Hari terakhir yang dihitung = tanggal Gate-Out Full.
DETENTION : Hari 0 = tanggal Gate-Out Full. Berakhir saat Gate-In Empty.
→ Tanggal gate-out adalah HARI TERAKHIR demurrage SEKALIGUS Hari 0 detention — TIDAK dihitung ganda.
```

### 4.2 Konfigurasi per line (bukan asumsi global — Q9)
Tiap line memiliki konfigurasi sendiri di master:
- `free_days_demurrage` dan `free_days_detention` (boleh berbeda);
- `start_offset` per jenis biaya: **H+0 atau H+1**;
- `tipe_kalender`: **kalender penuh atau hari kerja**;
- `mode`: **terpisah atau gabungan** (line tertentu menggabungkan demurrage+detention; rincian aturan gabungan dikonfirmasi dari dokumen tarif masing-masing line saat konfigurasi — Lampiran E).

### 4.3 Tarif Line ber-versi (Q9)
- Kunci: **line + jenis biaya (demurrage/detention) + ukuran kontainer (20'/40'/40'HC) + tier hari ke-berapa + tanggal berlaku**. Tarif 20' dan 40' hampir selalu berbeda.
- Perubahan tarif **tidak pernah berlaku retroaktif** pada job lama.
- **Angka contoh (mis. USD 35/65/110) lenyap dari kode dan dari spesifikasi** — di seed data hanya boleh muncul sebagai contoh bertanda dummy, bukan tarif default.
- **Estimasi berjalan** di dashboard memakai tarif berlaku hari ini; **biaya final dikunci setelah gate-in**, memakai **tarif yang berlaku pada periode keterlambatan kontainer itu** — hari demi hari sesuai versi tarif masing-masing *(penajaman v2.1)* — lalu direkonsiliasi dengan invoice line bila ada. Prinsip non-retroaktif melindungi job yang sudah final, bukan kontainer yang masih berjalan melewati pergantian tarif.
- **Last free date adalah field yang dihitung** (tanggal patok + Free Time per konfigurasi line §4.2), bukan field yang diinput manual *(v2.1)*.

```python
def hitung_biaya(kontainer, konfig_line, tarif_line, hari_referensi):
    # hari_referensi = hari ini (estimasi berjalan) atau tanggal gate-in (final)
    hari_dem = hari_kalender_tipe(konfig_line, discharge, gate_out_full or hari_referensi,
                                  offset=konfig_line.start_offset_dem)
    demurrage = tiered(max(0, hari_dem - konfig_line.free_days_dem),
                       tarif_line["demurrage"][kontainer.ukuran], tanggal_berlaku)

    if gate_out_full is None:
        detention = 0
    else:
        hari_det = hari_kalender_tipe(konfig_line, gate_out_full, gate_in_empty or hari_referensi,
                                      offset=konfig_line.start_offset_det)
        detention = tiered(max(0, hari_det - konfig_line.free_days_det),
                           tarif_line["detention"][kontainer.ukuran], tanggal_berlaku)

    return demurrage + detention   # mode gabungan memakai satu pool free time per aturan line
```

### 4.4 Scheduler & alarm (Q8, Q9)
- **Cron harian 00:00** menghitung sisa Free Time semua kontainer yang aktif + **recompute setiap perubahan milestone**.
- Lampu indikator **per Kontainer** (ditampilkan juga di daftar job — Q27a):
  - **HIJAU (SAFE):** sisa > 3 hari.
  - **KUNING (WARNING):** sisa 1–3 hari → notifikasi ke PIC Job + Finance (§2.6; penerima internal, bukan blast ke Importir).
  - **MERAH (CRITICAL):** sisa ≤ 0 → biaya kerugian harian berjalan tampil *live* di dashboard operasional, dihitung dengan tarif berlaku + kurs hari ini.

---

## 5. TRUCKING & SURAT JALAN (Q13)

- **Vendor pihak ketiga**; master vendor dengan `vendor_type: eksternal / internal` **dari awal** (armada sendiri = fase berikutnya tanpa refactor).
- **Harga per rute ber-versi**: asal (pelabuhan/depot) → tujuan (gudang) + ukuran kontainer + tanggal berlaku; wajib jelas cakupannya (tol, bongkar, biaya tunggu) agar selisih vendor invoice terlacak.
- Alur: Ops pilih vendor → **Assigned** → vendor/jadwal dikonfirmasi → **Delivered**. Biaya saat assignment = **estimasi**; biaya final setelah invoice vendor masuk; **selisih terlihat di job**.
- **Surat Jalan per trip per kontainer** (truk 40' = 1 kontainer; truk 20' bisa 1–2 kontainer). **Bukan per Job.**
- **Biaya trucking** dicatat per kontainer — atau **per trip** bila satu trip membawa dua kontainer 20' *(v2.1)* — selalu dengan mata uang asal, snapshot Kurs Internal, dan bukti (invoice vendor).
- Isi wajib Surat Jalan: nomor job, nomor kontainer, nama driver, nopol, nomor HP driver.
- POD (§2.4) ter-link ke Surat Jalan.

---

## 6. BIAYA, DEPOSIT, TITIPAN DANA, INVOICE

### 6.1 Ledger real-cost per Job (Q16)
Komponen minimal (semua **pass-through**, tanpa markup): ocean freight, THC, demurrage, detention, DO fee, admin/terminal line, trucking, karantina/lartas (bila ada) — **plus komponen Jasa PPJK**.
- **Ocean freight punya penanda pembayar:** *prepaid dibayar langsung oleh importir* → tetap dilacak untuk kebutuhan CIF di Kalkulator PIB, ditandai **"dibayar langsung, tidak ditagih"**; *collect dibayar PPJK* → masuk tagihan sebagai pass-through. Tanpa penanda ini, freight double-count.
- Tiap baris biaya menyimpan: **mata uang asal, snapshot Kurs Internal yang dipakai, bukti, status Estimasi/Aktual**.
- Biaya pihak ketiga menyimpan **DPP & PPN dari faktur vendor apa adanya** — sistem tidak menghitung ulang; faktur tanpa PPN disimpan 0 **dengan alasan**.
- **Tanpa bukti, baris tidak bisa masuk draf invoice** *(v2.1)*.
- **Jasa PPJK tidak pernah dihitung sebagai persentase dari CIF** *(v2.1)* — flat per Job (default) atau per kontainer (opsi master).
- **Tidak ada bunga talangan otomatis di v1** *(v2.1)* — talangan pungutan dicatat sebagai uang muka/settlement (§6.3), bukan fee tersembunyi.

### 6.2 Deposit Kontainer (ADR 0005)
- **Ledger terpisah, dicatat per Kontainer** (bukan per Job): jumlah, tanggal dibayar ke line/depot, status **Ditahan / Dikembalikan penuh / Dikembalikan sebagian / Hangus sebagian / Hangus penuh**. Total per Job hanya tampilan agregat.
- Baru pindah ke ledger biaya **bila hangus**, **sebesar porsi yang hangus**, dengan bukti **debit note** dari line. Sisa deposit yang kembali **tidak pernah masuk invoice**.
- **Deposit ≠ Titipan Dana** — dua pot dana berbeda.

### 6.3 Pembayaran, alokasi 2-langkah & Titipan Dana (Q18, Q22)
- v1 **hanya mencatat** pembayaran — tidak memblokir proses secara default. Catatan minimal: tanggal terima, jumlah, metode, referensi kas bon/transfer, dan alokasi.
- **Alokasi 2 langkah:** Finance pilih Job/invoice tujuan → alokasi **manual per baris** di dalam invoice. Uang tanpa referensi invoice **tidak pernah otomatis dialokasi ke Job tertua** — itu keputusan Finance.
- Batasan tegas: alokasi per baris ≤ sisa tagihan baris; total alokasi ≤ jumlah pembayaran. Saran urutan sistem = **draf** (default: pungutan negara → pihak ketiga → jasa; urutan dikonfigurasi). **Penyimpangan dari urutan wajib beralasan tercatat.** **Pro-rata otomatis tidak diimplementasikan.**
- Sisa tak teralokasi → **Titipan Dana** per importir (saldo + asal pembayaran + tanggal + riwayat alokasi). Status baris biaya: `Belum dibayar / Dibayar sebagian / Lunas`.
- Dua saklar opsional per Importir (**terpisah**): "wajib pelunasan sebelum filing" dan "wajib pelunasan sebelum gate-out". Bila aktif dan milestone dicoba → sistem **menolak**, tapi Finance/Admin dapat **override dengan alasan tercatat**.
- Dashboard **eksposur per Importir**: *eksposur bruto* = total real-cost Aktual − total alokasi; **Titipan Dana tampil sebagai kolom terpisah** yang dapat menutup eksposur — tidak dikurangkan diam-diam.
- Close Job tetap mensyaratkan lunas (§2.3), walau gate-out boleh terjadi sebelum lunas.

### 6.3.1 Uang muka, settlement & definisi lunas *(v2.1 — model baru dari draft independen)*
- **Uang muka per Job** = estimasi pungutan dari Kalkulator PIB + estimasi biaya line/trucking + Jasa PPJK — **bukan persentase sembarangan**.
- **Alur:** Importir transfer uang muka → PPJK membayar billing code dan menyimpan **NTPN** (ditempel ke Snapshot PIB yang difile, §3.7). Bila Importir membayar billing code langsung ke negara, PPJK hanya mencatat nomor billing code & NTPN dari Importir — **pola pengecualian yang dicatat**, bukan default ganda.
- **Settlement:** tagihan final dikurangi uang muka; **sisa dikembalikan dan tidak otomatis dibawa ke Job berikutnya** tanpa instruksi tertulis Importir. Syarat close mencakup settlement uang muka = 0 (§2.3).
- **Definisi lunas:** total invoice − uang muka − pembayaran yang sudah dicocokkan = 0, dan Finance mengonfirmasi bukti bayar. Pembayaran parsial diperbolehkan. **Tanpa pencocokan otomatis mutasi bank di v1; tanpa denda keterlambatan Importir di v1** — hanya pengingat dan Problem Flag.
- **Demurrage/detention yang diperselisihkan:** default v1 **tetap ditagihkan ke Importir sebagai pass-through** dengan catatan penyebab keterlambatan per kontainer; v1 tidak membagi tanggung jawab otomatis antara Importir dan PPJK. Bila PPJK setuju menanggung sebagian → Problem Flag Dispute + nota kredit manual, bukan logika sistem yang menebak siapa salah.

### 6.4 Invoice 3 blok (Q17, Q31, ADR 0004)
| Blok | Isi | Perlakuan pajak |
|---|---|---|
| **(a) Pungutan negara** | BM, PPN Impor, PPh 22 (+PPnBM/pungutan lain) — **dari Snapshot SPT PIB, bukan dari Kalkulator** | Disbursement, **tanpa PPN atas jasa**; **PPN Impor bukan PPN Keluaran PPJK** — tidak dijumlahkan sebagai PPN yang disetor PPJK; angka = angka snapshot yang sudah dibulatkan statutori, tidak dihitung ulang |
| **(b) Biaya pihak ketiga** | THC, trucking, dll. | **DPP & PPN disimpan apa adanya dari faktur vendor**; PPN mengikuti faktur |
| **(c) Jasa PPJK** | Service fee | **PPN 12% × DPP Nilai Lain 11/12** (= 11% efektif) atas **nilai jasa itu sendiri**; tarif dari **master berversi**, bukan hardcode; e-Faktur/lembar invoice menampilkan DPP Nilai Lain 11/12 eksplisit |

- Service fee: **default flat per Job**; opsi **per Kontainer** di master (tarif per kontainer + tanggal berlaku + override per Importir).
- **Pass-through terbukti sistem:** jumlah ditagih = jumlah aktual setelah konversi Kurs Internal; selisih hanya lewat penyesuaian yang disetujui dengan alasan tercatat.
- Tiap blok menyimpan **DPP, tarif, dan PPN** — bukan hanya total.
- Invoice menyimpan **snapshot kurs**: jumlah valas asli + kurs + hasil IDR. **Kurs final invoice mengunci** pada tanggal invoice; estimasi di dashboard memakai kurs hari ini (boleh berubah).
- Tagihan dalam **IDR**; invoice USD = pengecualian manual di luar sistem (v1).
- **Nomor invoice berurutan tanpa lompatan** *(v2.1)*. Invoice yang sudah terkunci tidak boleh diedit; koreksi hanya lewat **nota kredit / nota debit baru** yang ter-link ke invoice asal.
- **Faktur pajak v1 cukup dicatat nomornya** dan ter-link ke invoice *(v2.1)* — pembuatan e-Faktur tetap di luar sistem.
- Tiap baris biaya punya flag **"kena PPN jasa: ya/tidak"** *(v2.1)* — Demurrage/Detention yang hanya diteruskan tanpa markup tidak diperlakukan sebagai Jasa PPJK.
- **Alur approve** *(v2.1)*: Ops/CS mengisi real-cost + bukti (status Draf) → Finance memverifikasi bukti, Kurs Internal, hitungan demurrage/detention terhadap milestone per kontainer, dan kecocokan Kalkulator PIB vs SPT PIB → Approver menyetujui (mengikuti Batas Approve §6.5) → invoice terkunci → dikirim. **Draf dibuat otomatis dari ledger; pengiriman tidak pernah otomatis.**

### 6.5 Batas approve invoice (Q33, ADR 0007)
- **Batas nominal per user Finance** (bukan peran ke-7), berlaku atas **total invoice DI LUAR Blok Pungutan Negara** (blok pihak ketiga + blok jasa) — blok pungutan terkunci Snapshot PIB dan sudah melewati maker-checker, tidak ada diskresi Finance di situ.
- Melewati batas → wajib disetujui user Finance lain dengan batas lebih tinggi. **"Direktur" = user Finance dengan batas tanpa batas**, tetap tidak boleh menyetujui invoice buatannya sendiri.
- Perubahan batas oleh Admin dengan tanggal berlaku. Placeholder **Rp50.000.000** pada base itu; angka riil dikunci saat go-live.

### 6.6 Kurs Internal (Q14, Q24)
- Kunci: **tanggal + company + kode mata uang**; satu resmi per hari per company per mata uang; ditetapkan Finance dengan jam cutoff (mis. 09.00).
- Riwayat **tidak dapat diedit** setelah dipakai invoice.
- Mata uang **nonaktif ditolak dengan pesan jelas** — tidak dikonversi diam-diam dengan kurs lama (master mata uang punya flag aktif + tanggal berlaku).
- **Kurs hari itu belum di-set → invoice final DIBLOKIR**; draf boleh memakai kurs terakhir dengan flag *"kurs belum resmi hari ini"*. Tidak pernah memakai kurs kemarin untuk invoice final tanpa flag.
- Layar biaya Job menampilkan **Kurs Pajak dan Kurs Internal berdampingan dengan label jelas** (kurs pajak tersimpan di level PIB; komersial di level invoice).

---

## 7. LAPORAN v1 (Q27)

| # | Laporan | Definisi terkunci |
|---|---|---|
| a | Daftar Job | Filter Status Progres, Problem Flag, Importir, line, rentang tanggal; tampilkan **countdown Free Time per Kontainer** |
| b | Eksposur per Importir | **Eksposur bruto** (real-cost Aktual − alokasi) + **kolom Titipan Dana terpisah** |
| c | Piutang & aging | Aging dari **tanggal jatuh tempo** (terms per Importir, mis. +14 hari); bucket 0–30 / 31–60 / 61–90 / >90 hari |
| d | Estimasi vs aktual | Kalkulator vs SPT PIB, dan estimasi per baris vs aktual — IDR & persen + alasan selisih |
| e | Denda per line | Total hari terlambat, total USD, total IDR — per line, per ukuran kontainer, per periode; rata-rata waktu ke gate-out & gate-in (bahan negosiasi tarif) |
| f | Arus deposit kontainer | Ditahan / kembali penuh / sebagian / hangus sebagian / hangus penuh + **deposit ditahan lama sebagai tindak lanjut** |

Ekspor **Excel untuk (b), (c), (d), (f) masuk v1**. Akses laporan mengikuti matriks peran §2.5 — pemetaan v2.1: **Finance:** (b)(c)(d)(f); **Ops:** (a)(e); **Customs Specialist:** bagian PIB dari (d); **Field:** tidak ada akses laporan.

---

## 8. TEKNOLOGI & DEPLOYMENT

### 8.1 Prinsip (Q25, Q34)
- **Cloud region Indonesia**, **managed services** (bukan VPS mentah — beban patch/backup tidak boleh jatuh ke tim 5–15 orang). On-prem ditolak: maintenance, risiko listrik/kehilangan data, akses Field di jalan.
- Provider wajib memberi **DPA + komitmen residensi data termasuk lokasi backup**; replikasi backup ke luar Indonesia harus ketahuan di riset PDP, bukan belakangan. Provider konkret dipilih saat build dengan checklist dari file riset.
- HTTPS wajib; akses dari HP (Field).
- **Stack teknologi terkunci (ADR 0009):** **Laravel (PHP 8.2+) + Inertia.js (React/TypeScript) PWA + PostgreSQL + Docker**. Monorepo satu atap; uang wajib menggunakan tipe `NUMERIC(18, 4)` di database dan `decimal` di model Eloquent; migrasi database hidup; pengujian otomatis wajib untuk kalkulator uang dan engine demurrage/detention; `DEBUG` mati di produksi; database managed di Cloud Region Indonesia. PWA mobile menangani simpan draf offline untuk Field di pelabuhan.
- Provider konkret dipilih saat build dengan checklist dari file riset. Nama provider tidak dikunci di dokumen ini.

### 8.2 Backup & arsip
- Backup harian: **database + file bukti yang diupload** (bukan database saja); retensi **30 hari**; **uji restore tiap kuartal**.
- **Backup ≠ arsip**: arsip dokumen 10 tahun (§1.5) terpisah dari backup dan dapat diekspor sebelum penghapusan.

### 8.3 Lingkungan
- **Staging terpisah dari produksi** dan **tidak boleh berisi data pribadi riil Importir** — staging dengan data riil = sistem produksi kedua dengan kewajiban PDP yang sama.
- Kredensial cloud dipegang **Admin**, tidak tersebar di laptop.

### 8.4 Runbook insiden kebocoran data (UU PDP Ps. 46 — 3×24 jam)
Isi minimal: deteksi → containment → pelestarian log → daftar kontak → langkah notifikasi 3×24 jam ke subjek data & lembaga berwenang (nama lembaga ditulis generik sampai status kelembagaan pengawas PDP terverifikasi [PV-12]). Masuk v1, bukan formalitas.

### 8.5 Multi-valas & template (Q24, Q26)
- Struktur multi-valas sejak awal (USD, CNY, EUR, JPY; kurs per mata uang); **v1 hanya USD aktif**; tagihan IDR.
- Semua dokumen keluaran (Lembar Kerja PIB, Surat Jalan, Memo Tagihan) dari **template ber-versi** dengan satu sumber data Job.

---

## 9. FASE BERIKUTNYA (di luar v1 — tercatat agar tidak bocor masuk diam-diam)

Integrasi CEISA 4.0 host-to-host (§3.10) · WhatsApp Business API · OCR dokumen asal · invoice USD · aktivasi multi-tenant · armada sendiri (`vendor_type: internal` sudah disiapkan) · proses refund MFN→FTA · LCL/konsolidasi · ekspor/udara/gudang.

---

## LAMPIRAN A — ISTILAH
Sumber tunggal: `GLOSSARY.md` (PPJK, Importir, Job, Kontainer, Milestone, DO, Demurrage, Detention, Free Time, Tarif Line, Surat Jalan, Kurs Internal, Kurs Pajak, Status Progres, Problem Flag, Cabang Pabean/Logistik, Real-cost, Pass-through, Disbursement, Jasa PPJK, Deposit Kontainer, Titipan Dana, POD, Snapshot PIB, Maker-Checker, Batas Approve, Blok Invoice, Nilai Pabean, Nilai Impor, Seri Barang). Istilah repo publik (Shipment, Consignee, Task, dollar_rate, dsb.) **tidak dipakai**.

## LAMPIRAN B — INDEX ADR
| ADR | Keputusan |
|---|---|
| 0001 | Skema siap multi-tenant meski deploy internal tool |
| 0002 | Status job = proyeksi milestone, bukan kolom manual |
| 0003 | Dua kurs terpisah: Kurs Pajak (PIB) vs Kurs Internal (tagihan), snapshot immutable |
| 0004 | Invoice pungutan negara dari SPT PIB, bukan dari Kalkulator |
| 0005 | Deposit kontainer: ledger terpisah, per kontainer |
| 0006 | Maker-checker ditegakkan per user ID, bukan per peran; Admin bukan superuser |
| 0007 | Batas approve invoice di luar Blok Pungutan Negara |
| 0008 | Master tarif berversi dengan status verifikasi hukum; blokir di filing |
| 0009 | Pilihan Stack Teknologi: Laravel, Inertia.js (React/TypeScript) PWA, PostgreSQL, Docker |

## LAMPIRAN C — CHECKLIST VERIFIKASI PDF JDIH (pra-produksi)

Status per **2 November 2026** — hasil agen verifikasi, laporan lengkap: `docs/research/verifikasi-jdih-pv.md`. Legenda: **T** = Terverifikasi (tertutup), **S** = Sekunder-terkonfirmasi (dasar hukum sah, kutipan pasal tersedia, PDF resmi sebagai arsip), **B** = Masih terbuka (nonaktif/diblokir sampai ditutup).

| # | Item | Perlu dicek | Status |
|---|---|---|---|
| PV-1 | Kelanjutan PMK 11/2025 (akhir 2026?) | Mekanisme DPP 11/12 masih berlaku | S |
| PV-2 | Threshold USD 50 FOB | Ada/tidaknya di PMK 51/2025 | S — **tidak ada**; jangan diimplementasikan |
| PV-3 | Periodisasi KMK Rabu–Selasa | Pasal PMK 66/2017 / perubahannya | S — Rabu–Selasa terkonfirmasi; NDPBM = Nilai Dasar Pabean Menteri Keuangan |
| PV-4 | Asuransi 0,5% × FOB (varian CFR) | Pasal persis di PMK 144/2022 | S — asuransi dalam negeri = 0 |
| PV-5 | BM < Rp25.000 | Penerus PP 84/2001; per seri atau per PIB | **B** — tetap nonaktif |
| PV-6 | Ambang Nilai Impor PPnBM | Angka persis PP 73/2019 | S — USD 30k/50k terkonfirmasi |
| PV-7 | Perubahan BTKI | Isi & cakupan PMK 62/2025 | S — BTKI 2022 + PMK 62/2025 |
| PV-8 | Daftar HS lengkap Lampiran A/B PPh 22 | PDF lampiran PMK 51/2025 | **T** — 215 pos tarif diekstrak → `docs/research/seed-pph22-lampiran.csv` |
| PV-9 | Tarif preferensi FTA | Pembaruan PMK 88/2023 untuk 2026 | S — berlaku s.d. 2027; IEU-CEPA belum |
| PV-10 | e-SKA per skema | Status e-Form ANZ; dasar AIFTA | S — e-Form ANZ aktif; AIFTA = PMK 35/PMK.04/2023 |
| PV-11 | Pengecualian pemrosesan luar NKRI | Redaksi ayat PP 71/2019 Ps. 20 | S — merujuk UU PDP Ps. 56 |
| PV-12 | Lembaga pengawas PDP | Status kelembagaan per Nov 2026 | **B** — Perpres belum terbit; lapor ke lembaga generik/Komdigi |
| PV-13 | Redaksi UU Kepabeanan Ps. 29 | Untuk kontrak kuasa importir→PPJK | **T** — ayat (3)/(4) + sanksi Rp100 juta dikutip |
| PV-14 | Endpoint teknis API Kurs Kemenkeu | Registrasi token Layanan API Nilai Kurs | **B** — butuh registrasi; v1 fallback Excel/manual |
| PV-15 | Endpoint CEISA 4.0 Open API | Akun Portal CEISA + dokumen teknis DJBC | **B** — butuh akun; v1 filing manual (Lembar Kerja) |
| PV-16 | Daftar nilai pabean minimum per HS | Masih dipublikasikan DJBC atau tidak | **B** — tidak ada daftar publik |
| PV-17 | Data elektronik impor 10 th / "PMK 71/2026" | Aturan pengawasan terbaru | **B** — tidak ditemukan; retensi 10 th bersandar UU KUP |
| PV-18 | Update teknis registrasi PPJK pasca-2025 | PER-35/BC/2019 & KEP-222/BC/2022 | S — PMK 219/2019 masih acuan |
| PV-19 | Tanggal efektif IA-CEPA dari sumber resmi | Master preferensi | **T** — 5 Juli 2020 |

**Aturan:** item [PV-n] boleh dipakai draf estimasi dengan label peringatan; **Snapshot PIB untuk filing memblokir item berstatus B** (ADR 0008). Item berstatus S diizinkan untuk filing produksi dengan pencatatan nomor dasar hukum resmi di riwayat Snapshot PIB.
**Cara menutup checklist** *(v2.1)*: unduh PDF resmi dari JDIH → catat nomor & tanggal PDF → ubah status item master terkait menjadi **Terverifikasi** → catat dasar hukum di riwayat perubahan master (§3.6). Item yang gagal diverifikasi tetap nonaktif.

## LAMPIRAN D — REFERENSI GITHUB (BATASAN KERAS)
Direktif terkunci: repo publik (utama: `anwar-gazi/freightforward`; pembanding: AgileShift/cargo_management, dll. — lihat `docs/research/referensi-github.md`) dipakai **hanya sebagai referensi pola arsitektur & data model**. **Tidak ada fork sebagai basis build.** Klaim regulasi apa pun dari repo **tidak dipakai**. Pola yang diadopsi (dengan penamaan GLOSSARY kami): job costing Fixed/Per-unit, konversi mata uang berpasangan (+effective dating & dual-kurs ditambah), join table kontainer, pemetaan Debit Note↔costing, master DocType, satu-sumber-data-banyak-template-cetak, penamaan state eksplisit. Ditulis baru total: engine demurrage/detention, Kalkulator PIB v2 + Snapshot, validasi FTA, trucking, deposit, Titipan Dana, invoice 3 blok, audit trail + maker-checker.

## LAMPIRAN E — OPEN QUESTIONS CEISA & KOMERSIAL
1. **Pembulatan CIF Rupiah** sebelum dijadikan dasar — snapshot menyimpan presisi penuh sampai dikonfirmasi ke CEISA.
2. ~~Threshold USD 50 FOB (PV-2)~~ — **TUTUP 2 Nov 2026**: tidak ditemukan di PMK 51/2025; tidak diimplementasikan.
3. BM < Rp25.000: per seri barang atau per PIB (PV-5 — masih terbuka, tetap nonaktif).
4. **Aturan mode gabungan** demurrage+detention per line — dikonfirmasi dari dokumen tarif masing-masing line saat konfigurasi (komersial, bukan regulasi).
5. **Template urutan field Lembar Kerja PIB (§3.9)** — apakah masih cocok dengan layar CEISA 4.0 yang dipakai Customs Specialist saat ini; dicek saat pembuatan template pertama *(v2.1)*.

## LAMPIRAN F — INDEKS KEPUTUSAN Q1–Q34
Q1 PPJK berizin · Q2 scope FCL+trucking · Q3 internal tool, schema siap multi-tenant · Q4 kalkulator = estimator, struktur data = isian PIB CEISA · Q5 audit trail + arsip deactivate-only + retensi 10 th · Q6 skala 100–300 job/bln · Q7 greenfield, migrasi master data · Q8 email+badge+Telegram · Q9 demurrage/detention per line berversi · Q10 Job=importir+B/L, 1..N kontainer · Q11 status proyeksi milestone · Q12 milestone manual mobile + bukti + batch + backdate · Q13 trucking vendor + Surat Jalan per trip · Q14 dual kurs + snapshot invoice · Q15 DO independen, dua cabang · Q16 real-cost + payer flag freight + deposit per kontainer · Q17 invoice 3 blok + service fee · Q18 catat pembayaran + saklar blokir + eksposur · Q19 FTA gate-keeper · Q20 Snapshot PIB immutable · Q21 checklist 3 fase + status Diverifikasi · Q22 alokasi 2-langkah, tanpa pro-rata · Q23 RBAC 6 peran + maker-checker per user · Q24 multi-valas, kurs per mata uang · Q25 cloud Indonesia + managed + backup · Q26 Lembar Kerja PIB dari snapshot + template berversi · Q27 6 laporan + ekspor Excel · Q28 rumus v2 · Q29 4 master berversi + status verifikasi · Q30 ambang & kasus tepi · Q31 PPN per blok · Q32 pembulatan dua layer · Q33 batas approve di luar pungutan · Q34 deployment & UU PDP final.
**Pengayaan v2.1 (draft independen, non-Q):** migrasi NPWP + cutover · koreksi milestone tanpa hapus · syarat close #5 (settlement uang muka) · uang muka & definisi lunas (§6.3.1) · notifikasi H-2/H-0/H+1 internal · last free date dihitung + tarif final per periode · flag "kena PPN jasa" · nomor invoice + nota kredit/debit · alur approve invoice · pemetaan akses laporan · persyaratan stack · PV-16..PV-19 · koreksi NDPBM.

---
*Dokumen ini disusun dari sesi grill-with-docs 1 November 2026 (5 ronde, 34 keputusan) antara pemilik bisnis PPJK dan ZCode. Perubahan atas keputusan terkunci dilakukan melalui ADR baru, bukan dengan mengedit bagian ini diam-diam.*
