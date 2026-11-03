# 12: Ledger real-cost + Deposit Kontainer + Uang Muka/Titipan Dana

**What to build:** Finance/Ops mencatat biaya per job di ledger (ocean freight dengan penanda pembayar — prepaid oleh importir terlacak untuk CIF tapi "dibayar langsung, tidak ditagih"; THC; demurrage/detention final dari engine; DO fee; admin/terminal; karantina; trucking; jasa) — tiap baris: mata uang asal, snapshot Kurs Internal, bukti, status Estimasi/Aktual; tanpa bukti tidak masuk draf invoice. Deposit kontainer dicatat per kontainer (Ditahan/Kembali penuh/sebagian/Hangus sebagian/penuh); hanya porsi hangus pindah ke biaya dengan bukti debit note. Uang muka per job dibuat dari estimasi Kalkulator + biaya + jasa; settlement per job; sisa pembayaran tak teralokasi menjadi Titipan Dana per importir (saldo + riwayat); pembayaran billing-code langsung oleh importir dicatat sebagai pengecualian.

**Blocked by:** 08, 09.

**Status:** resolved

Sumber: §6.1–6.3, Q16, Q18, Q22, ADR 0005; definisi Uang Muka di GLOSSARY.

- [x] Freight prepaid-importir tidak muncul di tagihan namun tetap masuk CIF kalkulator (anti double-count) — `JobCost::isBillable()`
- [x] Deposit: lifecycle penuh; hangus sebagian → hanya porsi itu jadi biaya dengan debit note — `ContainerDeposit::postForfeitToLedger()`
- [x] Uang muka dari estimasi kalkulator; settlement & definisi lunas (§6.3.1) berfungsi — `UangMuka::settle()`
- [x] Titipan Dana per importir dengan riwayat alokasi; deposit ≠ titipan dana — `TitipanDana` + `TitipanDanaAllocation`
- [x] Test suite: 96 passed (283 assertions) di `LedgerDepositUangMukaTest`

## Catatan implementasi

1. Tabel `job_costs` dengan `payer_flag` (ppjk/importir_langsung) untuk anti-double-count freight.
2. Tabel `container_deposits` dengan lifecycle lengkap (ditahan → kembali/hangus) dan `postForfeitToLedger()`.
3. Tabel `uang_mukas` dengan settlement tracking per job.
4. Tabel `titipan_danas` + `titipan_dana_allocations` dengan saldo dan riwayat alokasi per importir.
