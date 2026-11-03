# 03: Master pihak & referensi (Importir, Line, Vendor, lokasi, kontainer, mata uang)

**What to build:** Admin mengisi master yang membuat operasional tak mengetik ulang: Importir (dedup berdasarkan NPWP — nama sama ejaan beda ditolak), Shipping Line, Vendor trucking dengan `vendor_type` eksternal/internal, pelabuhan/depot/gudang tujuan, jenis Kontainer (20'/40'/40'HC + tipe), dan master Mata Uang dengan flag aktif + tanggal berlaku (v1 hanya USD aktif).

**Blocked by:** 02.

**Status:** resolved

Sumber: §1.7, §2.1, §5.1, §6.6, Q7, Q10, Q24.

- [x] CRUD master Importir; dedup NPWP ditegakkan per company (`importirs` table + `Rule::unique` + `dedup_npwp_menolak_npwp_ganda_pada_company_yang_sama`)
- [x] Master Shipping Line, Vendor trucking (`vendor_type: eksternal / internal`), pelabuhan/depot/gudang (`locations`), jenis Kontainer (20'/40'/45' Dry/Reefer/HC) tersedia (`MasterDataController` + `MasterDataSeeder`)
- [x] Master Mata Uang: flag aktif + tanggal berlaku; USD default aktif; update tercatat di audit trail (`MasterDataTest > admin_dapat_mengubah_status_aktif_mata_uang`)
- [x] Semua master ber-`company_id` (`BelongsToCompany`), akses edit hanya Admin (Gate `can:master_data`), perubahan masuk audit trail (`Audit::record`)
- [x] UI Admin Master Data tabulasi lengkap (`resources/js/pages/admin/master/*` + `MasterTabs.tsx` + menu sidebar)
- [x] Test suite: 54 passed (144 assertions)

## Catatan implementasi

1. Tabel master `importirs`, `shipping_lines`, `vendors`, `locations`, `container_types`, dan `currencies` terisolasi per `company_id` dengan constraint unique scoped per company.
2. NPWP importir dicek keunikannya per company; duplikasi ditolak dengan pesan spesifik: *"Importir dengan NPWP ini sudah terdaftar (dedup NPWP, §1.7)"*.
3. Master data di-seed awal secara otomatis via `MasterDataSeeder`.
