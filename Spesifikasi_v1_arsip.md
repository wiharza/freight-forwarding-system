# ARCHITECTURE & SPECIFICATION DOCUMENT: WEB-BASED FREIGHT FORWARDING MANAGEMENT SYSTEM
## MODUL: IMPORT SYSTEM, ADVANCED PIB CALCULATOR & AUTOMATED DEMURRAGE MONITORING

Dokumen ini berfungsi sebagai spesifikasi teknis dan Standard Operating Procedure (SOP) digital untuk membangun Aplikasi Web Core Forwarding System.

---

### 1. ARSITEKTUR DIGITAL SISTEM & ALUR DATA WEB
Sistem web harus dirancang dengan arsitektur berbasis peran (Role-Based Access Control / RBAC) yang menghubungkan modul-modul berikut:

```
[Customer Service] -> Entri Data Order, PO, & Upload Dokumen Asal (Inv, PL, BL)
         │
         ▼
[Customs Specialist] -> Validasi HS Code, Preferential Tariff (Form E/D/AK), Kalkulator PIB Otomatis
         │
         ▼
[Operational & Field] -> Real-time Tracking (ATA, Gate-Out, Gate-In), Sistem Alarm Early Warning Demurrage
         │
         ▼
[Finance & Billing] -> Auto-Invoicing berdasarkan Real-cost Pelabuhan + Kalkulasi Denda Progresif
```

#### Komponen Skema Database Utama (Entities):
1. **Shipment Table:** ID, Job Number, Consignee, Shipper, POL, POD, Container Number, Size (20/40/45ft), Type (Dry/Reefer/Open Top), Vessel/Voyage, ETA, ATA.
2. **Customs Table:** ID, Shipment ID, HS Code, BM MFN Rate, FTA Scheme (Form E/D/AK/etc), FTA Rate, FOB, Insurance, Freight, NDR (Nilai Dasar Rumah Tangga / NDPBM), Billing Code.
3. **Milestone Table:** ID, Shipment ID, Discharge Date, DO Release Date, SPPB Date, Gate-Out Full, Gate-In Empty, Free Time Days.

---

### 2. LOGIKA MATEMATIS & ALGORITMA CODING UNTUK KALKULATOR PIB WEB

Sistem web harus mengotomatisasi perhitungan pungutan negara berdasarkan tarif normal (MFN) maupun tarif preferensi (Skema FTA seperti Form E, Form D, dll).

#### A. Input Parameter Sistem:
* `FOB`: Free on Board (Nilai Barang)
* `Insurance`: Asuransi (Jika dalam negeri / tidak ada, sistem auto-set `0.5% * FOB`)
* `Freight`: Ongkos angkut sesuai dokumen B/L atau manifest
* `NDPBM` (Nilai Dua Puluh Pabean Menteri Keuangan): Kurs Pajak otomatis ditarik via API Kemenkeu setiap hari Rabu.
* `Tarif_BM`: Persentase Bea Masuk (Disesuaikan berdasarkan input HS Code & Jenis Form Fasilitas).
* `Status_API`: Boolean (True jika memiliki API-U/API-P, False jika tidak memiliki).

#### B. Rumus Konversi Fiskal (Implementasi Backend):
```python
# Hitung Nilai Pabean dalam Rupiah
CIF_Valas = FOB + Insurance + Freight
CIF_Rupiah = CIF_Valas * NDPBM

# Skema Bea Masuk Khusus (Preferential Tariff)
if FTA_Fasilitas_Valid == True:
    # Contoh Form E (China-ASEAN), Form D (ATIGA), Form AK (Korea-ASEAN)
    # Umumnya Bea Masuk menjadi 0% atau tarif preferensi yang jauh lebih rendah dari MFN
    Tarif_BM = Tarif_Skema_FTA  
else:
    Tarif_BM = Tarif_MFN_Normal

# Komponen Pungutan Pajak PIB
Bea_Masuk = CIF_Rupiah * (Tarif_BM / 100)
Nilai_Impor = CIF_Rupiah + Bea_Masuk

PPN = Nilai_Impor * 0.11 # Tarif PPN 11%

if Status_API == True:
    PPh_22 = Nilai_Impor * 0.025 # 2.5% dengan API
else:
    PPh_22 = Nilai_Impor * 0.075 # 7.5% tanpa API

Total_Pungutan_PIB = Bea_Masuk + PPN + PPh_22
```

---

### 3. LOGIKA KONTROL DEMURRAGE & DETENTION ALARM SYSTEM

Sistem web wajib memiliki scheduler otomatis (Cron Job) yang berjalan setiap jam 00:00 untuk menghitung sisa waktu *Free Time* dan menghasilkan alarm visual di *Dashboard Operational*.

#### A. Aturan Logika Tanggal:
* `Discharge_Date` (Tanggal Bongkar Kapal) = Hari ke-0 masa Demurrage.
* `Free_Time_Demurrage` = Batas jumlah hari gratis penumpukan dari Pelayaran (misal: 7 hari).
* `Gate_Out_Full` = Tanggal kontainer keluar dari gerbang pelabuhan.

#### B. Rumus Denda Berjenjang (Tiering Rate System):
Sistem harus menggunakan struktur tabel tarif untuk kalkulasi biaya harian setelah melewati batas waktu bebas denda:

| Hari Keterlambatan | Tarif Denda Per Hari (Contoh Kasus Kontainer 20ft) |
| :--- | :--- |
| Hari 1 s.d 3 | USD 35 / Hari |
| Hari 4 s.d 7 | USD 65 / Hari |
| Hari 8 ke atas | USD 110 / Hari |

```python
def hitung_demurrage(discharge_date, gate_out_date, free_time, tarif_tier):
    hari_di_port = (gate_out_date - discharge_date).days
    hari_terlambat = hari_di_port - free_time
    
    if hari_terlambat <= 0:
        return 0, "AMAN (Free Time)"
        
    total_denda = 0
    for hari in range(1, hari_terlambat + 1):
        if hari <= 3:
            total_denda += 35
        elif hari <= 7:
            total_denda += 65
        else:
            total_denda += 110
            
    return total_denda, f"TERLAMBAT {hari_terlambat} HARI"
```

#### C. Spesifikasi UI/UX Dashboard - Lampu Indikator Kontainer:
* **STATUS HIJAU (SAFE):** `Sisa Hari Free Time > 3 Hari`. Berikan notifikasi sistem ke CS untuk memantau kelengkapan dokumen asli.
* **STATUS KUNING (WARNING):** `Sisa Hari Free Time 1-3 Hari`. Sistem otomatis mengirimkan Email Blast ke importir / operasional lapangan: *"Peringatan: Sisa Free Time < 48 Jam. Segera selesaikan penukaran DO atau proses SPPB!"*
* **STATUS MERAH (CRITICAL - DEMURRAGE CHARGES):** `Sisa Hari Free Time <= 0`. Sistem menampilkan biaya kerugian harian berjalan secara *live* pada halaman utama *User Interface* operasional.

---

### 4. FITUR UTAMA YANG WAJIB ADA PADA APLIKASI WEB FOWARDING

1. **Modul API Integration Bea Cukai / CEISA (Optional/Future Phase):** Sinkronisasi otomatis status PIB (Status: *Aju, Jalur Hijau/Kuning/Merah, SPPB*).
2. **Automated Document Generator:** Cetak lembar kerja internal, Surat Jalan Trucking, dan Memo Tagihan Jasa sekali klik berdasarkan data transaksi impor yang sudah diinput.
3. **Skema Tarif Preferensi Database:** Master data tarif bea masuk khusus (Form E, Form D, Form IJEPA) yang dapat diperbarui sewaktu-waktu sesuai regulasi PMK terbaru, sehingga pengguna cukup memilih jenis form dropped-down pada menu pengisian.

---
*Catatan Sistem Akhir: Seluruh data biaya valuta asing dalam sistem web wajib menggunakan multi-currency handler dengan tabel konversi harian terpusat guna menghindari selisih kurs pada laporan keuangan akhir.*
