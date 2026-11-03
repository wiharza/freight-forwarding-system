# 23: Lengkapi 12 halaman UI Inertia yang hilang

**What to build:** Audit menemukan 12 komponen Inertia dipanggil controller tapi file `.tsx` tidak ada → halaman error di browser (backend + test tetap hijau karena test tidak me-render page): `calculator/pib`, `admin/master/tax-rates`, `finance/invoices`, `finance/invoice-create`, `finance/invoice-show`, `finance/exposure`, `trips/index`, `trips/create`, `trips/show`, `surat-jalan/create`, `surat-jalan/show`, `vendor-routes/index`. Buat semua mengikuti pola halaman yang sudah ada.

**Blocked by:** None.

**Status:** resolved

- [x] Kalkulator PIB (form Seri Barang + hasil + snapshot)
- [x] Kurs Pajak (admin/master/tax-rates)
- [x] Invoice index / create / show + Exposure
- [x] Trips index / create / show + Surat Jalan create / show
- [x] Vendor Routes index
- [x] `npm run build` hijau


## Catatan implementasi

- Bonus: endpoint `jobs.calculator.approve` ditambahkan (draf PIB → Snapshot PIB, ADR 0004/0006) karena rantai Kalkulator→Invoice sebelumnya buntu.
- Bug fatal diperbaiki: `TitipanDana` tanpa import di `InvoiceController@exposure`.
- Sidebar: Trip Trucking, Invoice, Eksposur Importir.
