# Matriks Hak Akses Berdasarkan Implementasi

Keterangan: D = dapat menjalankan; V = hanya melihat/monitoring; S = terbatas scope; - = tidak memiliki route atau guard yang mendukung.

| Fitur/proses | Alumni | Admin Prodi | Admin Fakultas | Biro 3 | Superadmin |
|---|---|---|---|---|---|
| Login, logout, lupa/reset password | D diri sendiri | D | D | D | D |
| Ganti password wajib | D | - | - | - | - |
| Lihat/simpan profil sendiri | D | - | - | - | - |
| Update profil alumni | - | D, prodi sendiri | D, fakultas sendiri | V; update ditolak | D, universitas |
| Ajukan perusahaan baru | D | D melalui shared route | D melalui shared route | D melalui shared route bila role route mengizinkan | D melalui shared route |
| Isi tracer universitas | D diri sendiri | - | - | - | - |
| Isi tracer prodi | D diri sendiri | - | - | - | - |
| Kirim undangan atasan | D | D, scope | D, scope | D, scope | D |
| Isi evaluasi atasan | - | - | - | - | - |
| Dashboard | - | D, prodi | D, fakultas | D, universitas monitoring | D, universitas |
| Direktori alumni | - | D, prodi | D, fakultas | V, universitas | D, universitas |
| Detail alumni | - | D, prodi | D, fakultas | V, baca | D, universitas |
| Ekspor alumni | - | D, scope | D, scope | D monitoring | D universitas |
| Reminder satuan | - | D, scope | D, scope | D komunikasi | D |
| Blast email | - | - | - | D, target tidak lengkap | -/tidak ada route blast umum |
| Kelola instrumen universitas | - | - | - | - | D |
| Kelola instrumen prodi | - | D, prodi | D, prodi fakultas | - | D, semua/prodi |
| Kelola instrumen evaluasi atasan | - | - | - | - | D |
| Kelola akun alumni | - | D, prodi | D, fakultas | - | D, universitas |
| Master wilayah dan UMP | - | - | - | - | D |
| Master perusahaan | - | S, scope | S, scope | - | D, universitas |
| Verifikasi/reject perusahaan | - | D, scope | D, scope | - | D |
| Auto-replace perusahaan | - | D, scope | D, scope | - | D |
| LinkedIn single/batch | - | D, scope | D, scope | - | D |
| LinkedIn approve/plot/reject/history | - | D, scope | D, scope | - | D |
| Audit log | - | - | - | - | D |

## Enforcement

RoleMiddleware mencegah role yang tidak diizinkan. Controller dan service menambahkan scope query. Biro 3 memiliki route detail dan ekspor, tetapi method update profil mengembalikan warning read-only. Tidak ada policy class terpisah.
