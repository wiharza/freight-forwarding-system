# 10/13: Trucking + Surat Jalan per trip

**What to build:** Ops menugaskan vendor trucking per pengiriman: harga per rute berversi (asal→tujuan + ukuran kontainer + tanggal berlaku; kejelasan tol/bongkar/tunggu), alur Assigned → dikonfirmasi → Delivered. Surat Jalan dicetak per trip (truk 40' = 1 kontainer; truk 20' bisa 1–2 kontainer) memuat nomor job, kontainer, driver, nopol, HP driver, asal, tujuan, tanggal. Biaya trucking: estimasi saat assignment, final setelah invoice vendor, selisih terlihat di job dan masuk ledger (§6.1). POD = Surat Jalan bertanda tangan yang ter-link kembali ke trip.

**Blocked by:** 06, 12.

**Status:** resolved

Sumber: §5, §6.1, Q13.

- [x] Trip dengan 2×20' ditangani benar (satu trip dua kontainer; biaya per trip sesuai aturan §5)
- [x] Surat Jalan PDF memuat semua field wajib; tidak ada ketik ulang data
- [x] Selisih estimasi vs invoice vendor terlihat di job
- [x] POD ter-link ke Surat Jalan; checklist Pasca-Delivery menerima bukti gate-out/gate-in/POD
- [x] TripPolicy untuk authorization (view oleh peran non-Field atau Field yang ditugaskan)
- [x] VendorRouteController dengan validasi boolean + Rule::in untuk ukuran kontainer
- [x] Test suite: 90 passed (267 assertions) di `TruckingAndSuratJalanTest`

## Catatan implementasi

1. Tabel `trips` + `trip_containers` (pivot dengan `sequence` untuk urutan muatan) + `surat_jalans` + `vendor_routes` (harga per rute berversi) + `vendor_invoices` (untuk tracking estimasi vs final).
2. Trip mendukung 1 kontainer 40' ATAU 2 kontainer 20' per trip (pivot sequence).
3. Surat Jalan dibuat per trip dari data Job yang sama - tidak ada ketik ulang.
4. POD = Surat Jalan bertanda tangan yang ter-link kembali ke trip via `pod_file_path` + `pod_signed_at`.
5. VendorRoute menyimpan harga per rute dengan flag `include_toll`, `include_unloading`, `include_waiting` sesuai §5.1.
