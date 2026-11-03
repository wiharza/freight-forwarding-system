# Domain Docs

How the engineering skills should consume this repo's domain documentation when exploring the codebase.

## Before exploring, read these

- **`GLOSSARY.md`** at the repo root — bahasa kanonik proyek PPJK impor laut FCL (Job, Kontainer, Milestone, Kurs Internal, Kurs Pajak, Snapshot PIB, Titipan Dana, dst.). Istilah yang di-Avoid di glossary tidak boleh dipakai di output.
- **`docs/adr/`**: read ADRs that touch the area you're about to work in. ADR 0001–0009 terkunci; kontradiksi harus diangkat eksplisit, bukan diam-diam dilanggar.
- **`Spesifikasi_SOP_Sistem_Web_Forwarding.md`**: spesifikasi final v2.1 — sumber kebenaran fungsional; setiap bagian membawa jejak keputusan (Q/ADR). Setiap bagian kode harus bisa dilacak ke keputusannya.
- **`docs/research/regulasi-kepabeanan-dan-pajak.md`** + **`docs/research/verifikasi-jdih-pv.md`**: satu-satunya sumber hukum. Logika regulasi dari repo publik/luar TIDAK BOLEH dipakai (lihat ADR 0008 untuk gerbang status verifikasi).

## File structure

Single-context repo:

```
/
├── GLOSSARY.md
├── Spesifikasi_SOP_Sistem_Web_Forwarding.md   ← spec induk (v2.1)
├── docs/
│   ├── adr/                                   ← 0001–0009
│   ├── agents/                                ← konfigurasi skill engineering
│   └── research/                              ← riset regulasi + verifikasi JDIH + referensi GitHub
└── (kode Laravel + Inertia.js akan lahir di sini — ADR 0009)
```

## Use the glossary's vocabulary

When your output names a domain concept (in an issue title, a refactor proposal, a hypothesis, a test name), use the term as defined in `GLOSSARY.md`. Don't drift to synonyms the glossary explicitly avoids (Shipment, Consignee, Task, dollar_rate — istilah repo publik dilarang).

If the concept you need isn't in the glossary yet, that's a signal: either you're inventing language the project doesn't use (reconsider) or there's a real gap (note it for `/domain-modeling`).

## Flag ADR conflicts

If your output contradicts an existing ADR, surface it explicitly rather than silently overriding:

> _Contradicts ADR-0007 (batas approve di luar Blok Pungutan Negara), but worth reopening because…_
