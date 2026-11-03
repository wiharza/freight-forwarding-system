# 18: Master Vessel (nama kapal + IMO) — dropdown di form Job

**What to build:** `vessel_name` saat ini free-text → rawan salah ketik, dan IMO tidak dicatat sama sekali. Buat master `vessels` (nama, nomor IMO dengan validasi check digit, call sign, opsional shipping line). Form Job memilih kapal dari dropdown; nama kapal di-snapshot ke job (perubahan master tidak menulis ulang riwayat job). Validasi IMO: 7 digit + check digit (bobot 7..2 dari kiri, sum mod 10).

**Blocked by:** None.

**Status:** resolved

- [x] Migrasi `vessels` (company_id, name, imo_number, call_sign, shipping_line_id) + FK `vessel_id` di `jobs`
- [x] Model `Vessel` + trait `BelongsToCompany`; relasi `Job::vessel()`
- [x] Halaman admin/master/vessels + tab "Kapal" di MasterTabs + routes
- [x] `JobController@create` mengirim `vessels`; `store` menerima `vessel_id`, snapshot nama ke `vessel_name`
- [x] UI create.tsx: dropdown kapal (tetap ada input manual untuk data legacy Excel, §1.7)
- [x] show.tsx menampilkan IMO di samping nama kapal
- [x] Test: IMO valid diterima, check digit salah ditolak, vessel_id mengisi snapshot nama
