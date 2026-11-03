# 02: RBAC 6 peran + Maker-Checker per user ID + Audit trail

**What to build:** Admin membuat user dan memberi peran; satu user boleh multi-peran namun izin selalu dihitung dari matriks peran×fitur §2.5. Field hanya melihat job yang ditugaskan kepadanya. Pembuat tidak bisa menyetujui karyanya sendiri walau ia memegang dua peran (ADR 0006) — Admin bukan superuser. Setiap aksi terproteksi (terutama approval/override) tercatat di audit trail: siapa, kapan, apa, alasannya.

**Blocked by:** 01.

**Status:** resolved

Sumber: §2.5, §1.5, ADR 0006.

- [x] 6 peran eksplisit (Admin, CS, Customs Specialist, Ops, Field, Finance); multi-peran didukung; matriks peran×fitur ditegakkan di backend (`config/features.php` + `AppServiceProvider` Gates + `RbacMatrixTest`)
- [x] Maker-checker ditegakkan per user ID (bukan per peran): creator ≠ approver pada aksi approval (`App\Support\MakerChecker` + `MakerCheckerTest`)
- [x] Peran Admin tidak otomatis punya izin operasional (terbukti di test `admin_hanya_punya_fitur_master_data_dan_user_management` & `admin_tidak_mewarisi_fitur_operasional_saats_membuat_user`)
- [x] Tabel audit trail: siapa/kapan/objek/perubahan/alasan; tercatat otomatis untuk aksi approval & override; append-only tidak bisa diedit/dihapus (`App\Models\AuditTrail` + `App\Support\Audit` + `AuditTrailTest`)
- [x] Model penugasan Field→Job tersedia dan scoping Field ditegakkan (`field_job_assignments` + `Job::scopeVisibleTo` + `JobPolicy` + `FieldScopingTest`)
- [x] UI Admin Manajemen User (index, create, edit) dengan assignment multi-peran dan pencatatan audit trail (`admin/users/*` + `UserManagementTest`)

## Catatan implementasi

1. Tabel queue bawaan Laravel diubah namanya dari `jobs` menjadi `queue_jobs` di `config/queue.php` dan migrasi awal agar nama tabel `jobs` dapat murni digunakan untuk entitas domain forwarding `Job` sesuai GLOSSARY.
2. Kebijakan otorisasi Job dikelola via `App\Policies\JobPolicy` di mana non-Field dapat melihat semua job di company-nya, sedangkan Field hanya job yang ter-assign melalui `field_job_assignments`.
