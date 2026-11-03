# Infra Kepatuhan — Runbook & Checklist (Tiket 17)

## 1. Deployment Checklist (Q25, Q34, UU PDP)

| Item | Status | Keterangan |
|---|---|---|
| Cloud region Indonesia | ☐ | Wajib (UU PDP Ps. 55–56, PP 71/2019 Ps. 20) |
| Managed services (bukan VPS mentah) | ☐ | Managed DB + Managed Container |
| DPA + komitmen residensi (termasuk backup) | ☐ | Dari provider |
| Backup harian (DB + file bukti), retensi 30 hari | ☐ | Bukan hanya database |
| Uji restore kuartalan | ☐ | Jalankan sekali sebelum go-live |
| Staging terpisah + TANPA data pribadi riil | ☐ | Data sintetis saja |
| HTTPS wajib | ☐ | TLS certificate |
| DEBUG off di produksi | ☐ | APP_DEBUG=false |
| Database tidak terekspos publik | ☐ | Security group / firewall |
| Kredensial dipegang Admin | ☐ | Tidak tersebar |

## 2. Runbook Insiden Kebocoran Data (UU PDP Ps. 46 — 3×24 jam)

### Langkah 1: Deteksi (jam 0)
- Identifikasi sumber kebocoran
- Aktifkan incident response team

### Langkah 2: Containment (jam 0–4)
- Isolasi sistem yang terkompromi
- Rotasi kredensial
- Backup forensic

### Langkah 3: Pelestarian Log (jam 0–8)
- Export semua access log
- Freeze log rotation
- Simpan snapshot database

### Langkah 4: Kontak (jam 8–12)
- Internal: Direktur, IT Admin, Legal
- Eksternal: Lembaga pengawas PDP (nama generik — status kelembagaan [PV-12])
- Vendor cloud provider

### Langkah 5: Notifikasi 3×24 Jam (Ps. 46)
- Subjek data (importir yang terdampak)
- Lembaga berwenang (generik sampai [PV-12] terverifikasi)
- Isi notifikasi: apa yang terjadi, data apa yang bocor, langkah mitigasi, kontak

## 3. Backup & Restore Procedure

### Backup Harian
```bash
# Database
pg_dump -U ppjk -d ppjk > backup_$(date +%Y%m%d).sql

# File bukti (storage/app)
tar -czf storage_backup_$(date +%Y%m%d).tar.gz /var/www/html/storage/app/
```

### Restore Test (kuartalan)
```bash
# 1. Buat database baru
psql -U ppjk -c "CREATE DATABASE ppjk_restore_test"

# 2. Restore
psql -U ppjk -d ppjk_restore_test < backup_20261103.sql

# 3. Verifikasi data
psql -U ppjk -d ppjk_restore_test -c "SELECT count(*) FROM jobs"
# Expected: jumlah sesuai backup

# 4. Hapus database test
psql -U ppjk -c "DROP DATABASE ppjk_restore_test"
```

## 4. Arsip Dokumen 10 Tahun (UU KUP Ps. 28(1)c)

- Arsip = dokumen PIB, invoice, B/L, bukti bayar, Snapshot PIB
- Arsip terpisah dari backup (backup ≠ arsip)
- Arsip harus bisa diekspor sebelum penghapusan
- Retensi: 10 tahun dari akhir tahun buku
