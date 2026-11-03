# 0007 — Batas approve invoice dihitung di luar Blok Pungutan Negara

Konteks: pungutan negara pada invoice impor kerap jauh melebihi limit approval yang wajar, padahal blok itu adalah salinan terkunci dari Snapshot PIB (ADR 0004) tanpa kewenangan diskresi Finance di tahap invoice. Keputusan: Batas Approve per user Finance berlaku atas total invoice di luar Blok Pungutan Negara (blok pihak ketiga + blok jasa). Persetujuan kedua mengikat, persetujuan pertama tercatat sebagai review; "Direktur" adalah user Finance dengan batas tanpa batas yang tetap tidak boleh menyetujui invoice buatannya sendiri; perubahan batas dilakukan Admin dengan tanggal berlaku. Alternatif yang ditolak: batas atas total termasuk pungutan negara — Finance tak akan pernah bisa approve sendiri dan kontrolnya mati.

Konsekuensi: placeholder Rp50.000.000 atas base itu; angka riil dikunci saat go-live via konfigurasi Admin.
