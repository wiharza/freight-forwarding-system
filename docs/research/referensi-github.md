# Referensi GitHub — Analisis Repo Eksternal untuk Sistem Freight Forwarding PPJK

- Tanggal analisis: 2026-11-01
- Metode: **statis saja** (GitHub API + raw file). Tidak ada kode repo yang dieksekusi, tidak ada build/interpreter dari direktori repo. Salinan baca disimpan di `C:\Users\yanse\.zcode\tmp\ref-repos\freightforward\` (di luar workspace).
- Rujukan regulasi yang SAH: hanya `docs/research/regulasi-kepabeanan-dan-pajak.md` (workspace). **Semua klaim regulasi/logika tarif dari repo publik di bawah diberi label "TIDAK DIPAKAI"** — dipakai maksimal sebagai pola struktur, bukan rumus.

---

## Ringkasan Eksekutif

Tidak ada satu pun repo publik yang meng-cover kebutuhan inti kita (PPJK impor FCL Indonesia, demurrage/detention berversi, dual kurs, deposit ledger, maker-checker). Repo utama (`anwar-gazi/freightforward`) adalah **generator dokumen** Django untuk forwarder Bangladesh (C&F agent) — layak dibaca untuk pola dokumen, milestone checklist, dan job costing, tetapi **tidak ada logika regulasi Indonesia sama sekali**, kualitas teknis rendah (tanpa test, framework EOL, kebiasaan keamanan buruk), dan berlisensi **GPL-2.0** (copy kode dilarang; referensi konsep aman).

---

## 1. Repo Utama — anwar-gazi/freightforward

| Aspek | Fakta |
|---|---|
| Identitas | "FreightForward" — Resgef Labs, Dhaka, Bangladesh (C&F = Clearing & Forwarding agent, padanan PPJK) |
| Lisensi | **GPL-2.0** (teks penuh GPLv2 di `LICENSE`; README menyatakan copyleft) |
| Stack | Django 2.1.3 (EOL 2022) + PostgreSQL + template server-side + jQuery AJAX; PDF via `pdfkit`/wkhtmltopdf; `django-qr-code`; Heroku/Dokku (`Procfile`, `runtime.txt`, `app.json`) |
| Aktivitas | Dibuat 2021-07, push terakhir 2022-06, 58 commit, 11 bintang, default branch `initial_public_release_unstable` (nama eksplisit "unstable") |
| Ukuran | ~13.000 file / 128 MB; **12.421 di antaranya aset statis vendor** (Bootstrap/jQuery dsb. di-commit ke repo); kode aplikasi nyata hanya ~187 file |

### Struktur Folder
```
core/                  settings.py (Django 2.1), urls.py (18 KB rute monolitik), wsgi
packages/
  freightman/          app inti + air export: org, user, booking, MAWB/HAWB, consol,
                       job costing, debit/credit note, currency, AddressBook (79 KB models.py,
                       34 KB forms.py, 55 KB ajax_views.py)
  seaimport/           modul impor laut Bangladesh: MBL/HBL/Job/Task/DO/invoice/costing
  seaexport/           modul ekspor laut (+ master SeaPort, ContainerType, PackageType)
  airexport/           modul ekspor udara
tools/                 pg_dump/pg_restore helper, secret_key generator
```

### Model Data (entitas & relasi)
- **seaimport** (paling relevan dengan alur impor kita):
  - `SeaImportJob` — 1 Job punya `public_key` (job id), `dollar_rate` (float, default 83), 1—1 `SeaImportTask`.
  - `SeaImportMbl` (FK ke Job) dan `SeaImportHbl` (FK ke Job) — **MBL dan HBL tidak berelasi langsung**; keduanya hanya menunjuk Job. Consignor/consignee/notifier/bank = FK `SeaImportAgent`.
  - `SeaImportGood` — menempel ke MBL **atau** HBL; **kontainer hanya string `container_seal_number` + FK `ContainerType` (punya `capacity_cbm`)**. Tidak ada entitas Kontainer 1..N per B/L.
  - `SeaImportTask` — checklist 8 milestone boolean: Pre Alert → Forwarding Letter → MBL/HBL Confirm → IGM → BIN Number → Invoice → Freight Certificate → DO Issued; `job_progress()` = persen dari boolean.
  - Biaya: `SeaImportJobCost` (Job) dengan basis alokasi `Fixed / Per HBL / Per KG / Per CBM`; `SeaImportExpense` (per HBL, income); `SeaImportCreditNote` + costs. Profit = income HBL − cost Job.
  - `SeaImportDeliveryOrder` — field Bangladesh-specific (LC number, LCAF, IRC, BIN, TIN). `SeaImportDoc`/`DocType` — arsip dokumen scan per job.
- **freightman**:
  - `Organization` / `AddressBook` / `ServiceProviderMap` / role flags di Company (`is_forwarder`, `is_importer`, `is_bank`, `is_foreign_agent`).
  - `FreightBooking` — `is_draft`, `is_booking_confirmed`, `entry_by`, `confirmed_by/at` (pola draft→konfirmasi).
  - `MAWB`/`HAWB` — replikasi form IATA AWB (prepaid/collect, declared value, carrier agent). `AirExportConsolidatedShipment` + `ConsolHouseMap` (konsolidasi), job costing + debit/credit note per consol.
  - `CurrencyConversion` — satu baris rate (from/to/rate, `created_at`, `created_by`), **tanpa periode berlaku/valid_from**.
  - `AuthLevelPermissions` — **matrix boolean per kolom** (`can_access_seaimport_hbl_create`, ~40 kolom boolean) per organization + `UserAuthLevel`.
- **Tidak ada**: entitas kontainer sejati, demurrage, detention, deposit, milestone multi-cabang, kurs ganda, maker-checker, PIB/pajak impor, HS/tarif.

### Alur yang Diimplementasikan
1. Booking (draft → confirmed) → dokumen (MBL/HBL/AWB dibuat sebagai form + scan upload) → milestone checklist (seaimport) → DO/invoice/freight certificate (generate HTML→PDF) → job costing (alokasi cost ke HBL) → debit note (ke client) / credit note (ke agent) → invoice.
2. Konsolidasi (air export): consol + mapping house AWB + costing + note per consol.

### Kualitas & Kelengkapan
- Tanpa test (`tests.py` 60 byte; `user_test.py` di app root), DEBUG=True hardcode, `ALLOWED_HOSTS=['*']`, **kredensial default dicommit di file `setup guide`**, migrasi obsolete ikut dibawa, file placeholder 0-byte, dua sistem template paralel (`dict_builders*` + `dictbuilders/`), uang selalu `FloatField`, default branch "unstable", dokumentasi di situs gh-pages yang sebagian mati.
- Kesimpulan kualitas: **prototipe produk nyata yang berhenti di tengah jalan**. Berguna dibaca sebagai glosarium domain + alur, bukan sebagai basis kode.

### Klaim Regulasi di Repo → SEMUA "TIDAK DIPAKAI"
- `tax_prepaid`/`tax_collect` di HAWB/MAWB = kolom charges AWB IATA (biaya angkut), **bukan** perhitungan bea masuk/PPN → tidak dipakai.
- IGM, BIN Number, LCAF, IRC, dollar_rate=83 = konteks **Bangladesh** → tidak dipakai.
- Tidak ditemukan rumus PIB, tarif bea masuk, PPN/PPH impor, atau kurs NDPBM di repo → tidak ada yang bisa diadopsi. Rumus hanya dari `docs/research/regulasi-kepabeanan-dan-pajak.md`.

### Implikasi Lisensi GPL-2.0
- **Jangan menyalin kode** (termasuk template HTML, helper JS vendor) ke proyek kita — GPLv2 akan menulari turunan yang didistribusikan.
- Aman: membaca dan menuliskan ulang **konsep** (nama entitas, alur, jenis dokumen, urutan milestone) dengan implementasi sendiri — ide/alur tidak dilindungi hak cipta; kode ya.

### Kesesuaian dengan Konteks Kita (PPJK impor laut FCL)

| Kebutuhan sistem kita | Ada di repo? | Putusan |
|---|---|---|
| 1 Job = 1 importir + 1 B/L + 1..N kontainer | Sebagian (Job/MBL/HBL ada; **kontainer bukan entitas**) | Konsep Job-BL diadopsi; relasi kontainer desain sendiri |
| Milestone proyeksi dua cabang (pabean/logistik) | Sebagian (`SeaImportTask` checklist 8 langkah, single-flat) | Konsep checklist→progress diadopsi; wajib dimodelkan per-cabang, bukan flat boolean |
| Demurrage & detention berversi per tanggal berlaku | Tidak ada | Desain sendiri; tidak ada referensi dari repo ini |
| Dual kurs (NDPBM vs internal) | Anti-pattern (rate tunggal, float, tanpa validitas) | HINDARI polanya |
| Kalkulator PIB estimator | Tidak ada | Desain sendiri dari file regulasi internal |
| RBAC 6 peran + maker-checker per user ID | Anti-pattern (matrix boolean per kolom; konfirmasi booking hanya 1 langkah) | HINDARI polanya; pola `entry_by/confirmed_by` boleh jadi inspirasi minimal |

**ADOPSI (konsep/pola, ditulis ulang — bukan kode):**
1. **Pola document-generation**: master dokumen (DO, invoice, debit/credit note, BL/AWB, certificate) sebagai template → PDF (mereka pakai wkhtmltopdf; kita bebas pilih), plus arsip dokumen scan per job (`DocType`/`Doc`).
2. **Milestone checklist per job → persen progres** (`SeaImportTask`): cocok jadi kerangka awal proyeksi milestone dua cabang kita (ekspansi: tiap item punya cabang, timestamp, PIC).
3. **Job costing dengan master jenis biaya + basis alokasi** (`Fixed / Per HBL / Per KG / Per CBM`, nilai default per jenis) + debit note (piutang ke importir) vs credit note (biaya ke agent) — strukturnya pas untuk ledger deposit kontainer & rekap profit per job.
4. Bonus kecil: master `ContainerType` dengan `capacity_cbm`, master `PackageType`/`TermsofDelivery`/`PaymentType`, pembedaan pihak forwarder/importir/bank/agent via flag, pola `entry_by/confirmed_by/confirmed_at`.

**HINDARI:**
1. **Uang & kurs sebagai float dan rate tunggal tanpa masa berlaku** — langsung bertentangan dengan kebutuhan dual kurs berversi (NDPBM vs internal). Wajib decimal + tabel kurs dengan valid_from/valid_to + sumber.
2. **RBAC matrix boolean per kolom** — tidak scalable dan tidak bisa menampung 6 peran + maker-checker per user ID. Jangan ikuti `AuthLevelPermissions`.
3. **Tidak ada entitas kontainer** per B/L, tidak ada demurrage/detention/deposit — jangan menganggap repo ini "sudah meng-cover logistik".
4. Kualitas teknis: Django EOL, tanpa test, DEBUG/ALLOWED_HOSTS/kredensial buruk, monolit `ajax_views.py`, statis vendor 124 ribu file di-commit — **dan GPL-2.0** yang melarang copy kode.

---

## 2–5. Repo Alternatif (hasil pencarian, maks. 4 dipilih)

Query dilakukan: "github freight forwarding management system", "github PIB calculator bea cukai", "github import clearance system demurrage detention", "github customs brokerage software open source", plus GitHub API search (`bea masuk`, `pemberitahuan impor`, `freight forwarding`, `demurrage`, `customs declaration`).

Catatan pencarian:
- **Tidak ditemukan repo kalkulator PIB Indonesia yang layak** (hasil "bea masuk/PIB" didominasi false-positive *beancount*; kalkulator pajak Indonesia yang ada seperti `hmrdkn-labs/open-pajak` hanya PPh umum, bukan kepabeanan). Konfirmasi bahwa modul PIB estimator kita harus dibangun dari nol dengan rujukan file regulasi internal.
- `loadpartner/tms` (TMS freight broker AS, TypeScript) ditolak dari daftar utama: lisensi **Fair Core FCL-1.0-ALv2** (source-available, bukan open source; baru jadi Apache-2.0 beberapa tahun lagi per file) — hanya boleh dilihat konsepnya.
- Alat demurrage yang muncul (`Alpha-Park/genpark-*skills`, `velocortex-maritime`, `shippingrates-mcp`) berbentuk skill/plugin AI atau dashboard demo, tanpa skema tarif berversi yang bisa diadopsi.

### A. AgileShift/cargo_management — ERPNext Cargo Management
| Aspek | Fakta |
|---|---|
| Apa | Add-on ERPNext (Frappe) untuk freight forwarder: Cargo Shipment + line, Warehouse Receipt + line, Parcel & Parcel Content, Carrier + tracking URL |
| Lisensi | **AGPL-3.0** (menular lebih kuat dari GPL jika kode disalin & disajikan via jaringan) |
| Stack / aktivitas | Python/Frappe; dibuat 2020, **masih aktif (push 2026-10)**, 125 bintang |
| Kualitas | 12 doctype rapi, teruji di ekosistem ERPNext, tapi fokus ke kiriman domestik/trucking/warehouse — bukan customs |

- **Adopsi**: pola dokumen berlapis `Shipment → Shipment Line → Receipt → Receipt Line` (granularitas line-item di atas header) dan integrasi forwarder ke ERP (partner, invoice otomatis dari doctype). Jika kita nanti pakai ERPNext/Frappe, ini bukti pola jalan.
- **Hindari**: AGPL-3.0 (copy kode = seluruh proyek terekspos AGPL); domainnya bukan impor FCL/customs; tidak ada milestone pabean.

### B. logiic-ltd/customs — Customs Declaration Processing untuk Frappe
| Aspek | Fakta |
|---|---|
| Apa | Modul pengajuan deklarasi kepabeanan ala single-window: SAD (Single Administrative Document) + item, Manifest & Manifest Waybill, Waybill, Cargo Release Order, `TaxAssessmentNotice` + child table `TaxDetail` (tax_type / rate / base_amount / tax_amount), Customs Office/Role, user registration web form, **tarif di-import dari fixtures JSON + `tariff_loader`** |
| Lisensi | **MIT** (paling longgar dari semua kandidat) |
| Stack / aktivitas | Python/Frappe; dibuat 2025-02, push 2025-03, 5 bintang, 50 file — kecil/muda |
| Kualitas | Struktur doctype bersih dan konsepnya paling dekat ke PIB; belum terlihat produksi, formula tarif country-specific (SAD versi negara tertentu) |

- **Adopsi (struktur saja)**: (1) **dokumen deklarasi + line item + child table pajak per line** (`tax_type, rate, base_amount, tax_amount`) — pas sebagai pola awal estimator PIB per barang; (2) **tarif sebagai data (fixtures/loader), bukan hardcode** — langsung paralel dengan kebutuhan kita tarif/kurs berversi per tanggal berlaku; (3) peran pihak officer/broker/shipping agent/importer.
- **TIDAK DIPAKAI**: semua jenis tarif, bentuk SAD, dan rumus duty/VAT/excise di repo — country-specific (bukan Indonesia). Rujukan tetap `docs/research/regulasi-kepabeanan-dan-pajak.md`.
- **Hindari**: menganggapnya siap pakai (muda, kecil, belum teruji).

### C. hmrc/customs-declarations — UK HMRC Customs Declarations Service
| Aspek | Fakta |
|---|---|
| Apa | Layanan deklarasi kepabeanan resmi pemerintah UK (CDS): terima deklarasi dengan **skema WCO (DMS WCO 3.6)**, amend/cancel, upload dokumen pendukung, arrival notification, inventory linking (impor/ekspor), notifikasi status **asinkron** |
| Lisensi | **Apache-2.0** |
| Stack / aktivitas | Scala/Play + MongoDB, arsitektur microservice HMRC; dibuat ~2018, **masih aktif (push 2026-10)** |
| Kualitas | Kode produksi pemerintah dengan test & CI — standar engineering tertinggi di daftar ini |

- **Adopsi (pola)**: (1) **validasi payload terhadap skema sebelum masuk backend** + respons status asinkron untuk pengajuan deklarasi — pola ini tepat bila estimator PIB kita kelak dihubungkan ke portal nyata (CEISA) atau dikonsumsi sistem lain; (2) amandemen/pembatalan deklarasi sebagai operasi eksplisit (bukan edit bebas); (3) pemisahan dokumen pendukung dari dokumen utama.
- **Hindari**: replikasi skala/arsitektur microservice enterprise untuk sistem 1 kantor; semua detail skema & aturan UK/UE (TIDAK DIPAKAI untuk regulasi).

### D. fleetbase/fleetbase — Open-source Logistics OS
| Aspek | Fakta |
|---|---|
| Apa | Platform logistik modular (dispatch, fleet, order/fulfillment, tracking, API driver) — 4.472 bintang, paling besar & paling aktif (push 2026-11) |
| Lisensi | **AGPL-3.0** |
| Stack | Node.js (API) + Ember.js (console) + driver app |
| Kualitas | Sangat matang, API-first, extensible — tapi fokus last-mile/dispatch, bukan forwarding internasional/customs |

- **Adopsi**: (1) pola **event/status lifecycle per order** (order → activity/feed → tracking) sebagai referensi UX status job; (2) disiplin arsitektur API-first + modular extension.
- **Hindari**: AGPL-3.0; domain berbeda (bukan BL/kontainer/pabean); jangan tergiro menjadikannya basis sistem.

---

## Sintesis Akhir — ADOPSI vs HINDARI untuk Sistem Kita

**Peta kebutuhan → sumber referensi:**

| Fitur sistem kita | Repo referensi terbaik | Putusan |
|---|---|---|
| 1 Job = 1 importir + 1 B/L + 1..N kontainer | freightforward (Job/MBL/HBL), cargo_management (header-line) | Konsep Job–BL diadopsi; **entitas Kontainer & relasi 1..N desain sendiri** (tak ada referensi yang benar) |
| Milestone dua cabang (pabean/logistik) | freightforward `SeaImportTask` | Checklist → progres diadopsi sebagai konsep; wajib dimodelkan per-cabang dengan timestamp/PIC, bukan 8 boolean |
| Demurrage & detention berversi per tanggal berlaku | — (tidak ada di semua repo) | **Desain sendiri**; satu-satunya rujukan tarif adalah file regulasi internal |
| Dual kurs (NDPBM vs internal) | — (freightforward justru anti-pattern) | Desain sendiri: decimal + tabel kurs berversi (valid_from/valid_to) + sumber; jangan ikuti `CurrencyConversion` |
| Kalkulator PIB estimator | logiic-ltd/customs (pola line pajak), hmrc (pola validasi skema & status async) | Struktur line pajak + tarif-as-data + validasi skema diadopsi; **semua rumus/tarif dari repo = TIDAK DIPAKAI**, rumus dari file regulasi internal |
| RBAC 6 peran + maker-checker per user ID | logiic (konsep role pihak), Frappe roles | Anti-pattern `AuthLevelPermissions` dihindari; desain role table + approval workflow sendiri |
| Deposit kontainer ledger terpisah | — | Desain sendiri; adopsi konsep debit/credit note per job dari freightforward sebagai acuan rekap piutang/biaya |
| Dokumen (DO, invoice, note, certificate) + arsip scan | freightforward | **Adopsi terkuat**: pola template→PDF + DocType/Doc per job |

**Tiga hukum bawaan dari riset ini:**
1. **Copy tidak sama dengan adopsi.** GPL-2.0/AGPL-3.0 menutup jalur copy kode dari 3 dari 5 repo; MIT (logiic) dan Apache-2.0 (hmrc) terbuka, tapi isinya juga bukan domain kita. Semua adopsi di atas adalah adopsi *konsep/struktur* dengan implementasi sendiri.
2. **Tidak ada referensi tarif/kurs dari internet.** Setiap angka (rumus bea masuk, PPN, kurs, tarif demmurage) hanya dari `docs/research/regulasi-kepabeanan-dan-pajak.md`; repo eksternal hanya menyumbang *bentuk tabel* (child table pajak per line, tabel tarif as data, tabel kurs berversi).
3. **Gap terbesar pasar open source = exactly keunggulan kita.** Tidak ada repo yang meng-cover demurrage/detention berversi, dual kurs, deposit ledger, dan maker-checker — fitur-fitur inilah pembeda sistem kita dan harus didesain first-class dari awal.

## Jejak Pencarian (reproduksibilitas)
- Repo utama: `https://api.github.com/repos/anwar-gazi/freightforward`, tree `initial_public_release_unstable`, file dibaca: `README.md`, `LICENSE`, `core/settings.py`, `packages/freightman/models.py`, `packages/seaimport/models.py`, `old_requirements.txt`, `setup guide`.
- WebSearch: "github freight forwarding management system"; "github PIB calculator bea cukai"; "github import clearance system demurrage detention"; "github customs brokerage software open source".
- GitHub API search: `freight forwarding`, `customs declaration`, `demurrage`, `bea masuk`/`pemberitahuan impor` (false positive beancount).
- Kandidat dievaluasi & dipilih 4: `AgileShift/cargo_management`, `logiic-ltd/customs`, `hmrc/customs-declarations`, `fleetbase/fleetbase`. Kandidat lain tercatat: `loadpartner/tms` (lisensi Fair Core — ditolak), `mzaatar/FreightForwarding` (C#, tanpa lisensi), `Alpha-Park/genpark-*` demurrage skills (bukan aplikasi), `Seondong/Customs-Fraud-Detection` (dataset riset, bukan sistem).

Sources:
- [anwar-gazi/freightforward](https://github.com/anwar-gazi/freightforward)
- [AgileShift/cargo_management](https://github.com/AgileShift/cargo_management)
- [logiic-ltd/customs](https://github.com/logiic-ltd/customs)
- [hmrc/customs-declarations](https://github.com/hmrc/customs-declarations)
- [fleetbase/fleetbase](https://github.com/fleetbase/fleetbase)
- [loadpartner/tms](https://github.com/loadpartner/tms)
- [hmrdkn-labs/open-pajak](https://github.com/hmrdkn-labs/open-pajak)
