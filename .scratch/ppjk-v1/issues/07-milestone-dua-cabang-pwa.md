# 07: Milestone dua cabang + PWA mobile + entri batch/backdate + koreksi pengganti

**What to build:** Field menginput milestone fisik dari HP (PWA): kamera untuk bukti, form bisa simpan draf saat sinyal hilang, auto-kirim saat online. Milestone pabean (PIB difile, SPPB) dan logistik (discharge, DO, gate-out, terkirim, gate-in) dicatat independen per cabang; validasi kronologis dalam cabang MENOLAK (bukan memperingatkan); DO independen dan tak pernah membuat job mundur (penanda "DO sudah terbit"). Entri batch untuk beberapa kontainer; backdate dengan alasan; koreksi = Milestone pengganti yang merujuk milestone lama (tidak pernah dihapus); Ops memverifikasi dan tak bisa memverifikasi inputannya sendiri. Status Progres penuh: Gate-Out Sebagian/Selesai, Gate-In Sebagian/Selesai, dst.

**Blocked by:** 06.

**Status:** resolved

Sumber: §2.2, §2.3, §2.7, Q12, Q15, ADR 0002.

- [x] Entri offline di PWA → tersimpan lokal di localStorage HP saat sinyal pelabuhan hilang → auto-kirim saat online (`resources/js/pages/pwa/field-milestone.tsx`)
- [x] Validasi kronologis cabang menolak pelanggaran: SPPB ≥ PIB filed; DO ≥ discharge; Gate-Out ≥ DO & discharge; Gate-In ≥ Gate-Out pada kontainer yang sama (`Milestone::assertValidChronology`)
- [x] DO tercatat di level job kapan saja setelah discharge tanpa meregres status (penanda visual *"DO Sudah Terbit (§2.2)"* di UI)
- [x] Koreksi menghasilkan milestone pengganti (`supersedes_id`); milestone lama tidak pernah dihapus dan tetap tercatat di audit trail (`Milestone::supersedeWith`)
- [x] Status agregat Sebagian/Selesai benar untuk job multi-kontainer: `Gate-Out Sebagian/Selesai`, `Terkirim`, `Gate-In Empty Sebagian/Selesai` (`Job::recomputeStatus`)
- [x] Ops maker-checker: penginput milestone dilarang memverifikasi inputannya sendiri (`Milestone::verify` menegakkan `MakerChecker::assertApproverDiffers`)
- [x] Test suite: 79 passed (229 assertions) di `MilestoneAndPwaTest`

## Catatan implementasi

1. Tabel `milestones` membagi peristiwa ke dalam dua cabang terpisah: `branch: pabean` (PIB filed, SPPB) dan `branch: logistik` (discharge, DO, gate-out, delivered, gate-in).
2. Level milestone dibedakan secara tegas: discharge, DO release, PIB filed, dan SPPB berada di level Job (`container_id = null`), sedangkan Gate-Out Full, Delivered, dan Gate-In Empty dicatat per Kontainer fisik.
3. Form PWA mobile `pwa/field-milestone` dirancang ramah layar sentuh dan mendukung input batch untuk multi-kontainer sekaligus serta offline queue otomatis.
