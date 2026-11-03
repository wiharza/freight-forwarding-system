# 21: Incoterm pada draf PIB (dropdown Incoterms 2020)

**What to build:** Incoterm tidak dicatat di seluruh sistem, padahal PIB riil memuat kode incoterm dan menentukan perlakuan freight/asuransi. Tambah kolom `incoterm` (nullable, data keterangan — TIDAK mengubah logika DPP yang terkunci di Q28) pada `customs_declarations`, dropdown dari daftar Incoterms 2020: EXW, FCA, FAS, FOB, CFR, CIF, CPT, CIP, DAP, DPU, DDP.

**Blocked by:** None.

**Status:** resolved

- [x] Migrasi kolom + fillable + validasi `Rule::in` di `storeDraft`
- [x] Dropdown di kalkulator PIB
- [x] Test: CIF tersimpan; kode di luar daftar ditolak
