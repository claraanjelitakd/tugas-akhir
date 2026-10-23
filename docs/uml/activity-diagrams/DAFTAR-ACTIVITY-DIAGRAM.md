# Daftar Activity Diagram

| ID | Judul | Stakeholder utama | Swimlane | Hasil akhir | Keterkaitan |
|---|---|---|---|---|---|
| AD-01 | Autentikasi, pemulihan, perubahan password | Semua pengguna | Pengguna, Backend, DB, Mail | Session/token/password | Prasyarat semua route |
| AD-02 | Isi dan update profil alumni | Alumni/admin scope | Aktor, Backend, DB | Profil tersimpan | Sinkron profile ke tracer |
| AD-03 | Ajukan perusahaan alumni | Alumni | Alumni, Backend, DB | Company pending | Masuk AD-16 |
| AD-04 | Tracer universitas | Alumni | Alumni, Backend, DB | Tracer tersimpan | Status AD-21 |
| AD-05 | Tracer prodi | Alumni | Alumni, Backend, DB | ProdiResponse | Terpisah dari AD-04 |
| AD-06 | Undangan evaluasi atasan | Alumni/admin scope | Aktor, Backend, DB, Mail | Invitation sent | Membuka AD-23 |
| AD-07 | Direktori/detail/ekspor/reminder | Admin scope | Admin, Backend, DB, Mail | Data/report/notice | Biro 3 dirinci AD-08 |
| AD-08 | Monitoring dan blast Biro 3 | Biro 3 | Biro 3, Backend, DB, Mail | Monitoring/communication | Read-only detail |
| AD-09 | Dashboard prodi dan fakultas | Admin Prodi/Fakultas | Admin, Backend, DB | Dashboard scoped | Memakai AD-21 |
| AD-10 | Dashboard superadmin | Superadmin | Superadmin, Backend, DB | Dashboard university | Filter tahun |
| AD-11 | Instrumen universitas | Superadmin | Superadmin, Backend, DB | Instrument updated | Dipakai AD-04 |
| AD-12 | Instrumen prodi | Superadmin/Prodi/Fakultas | Aktor, Backend, DB | Instrument scoped | Dipakai AD-05 |
| AD-13 | Instrumen evaluasi atasan | Superadmin | Superadmin, Backend, DB | Active instrument | Dipakai AD-23 |
| AD-14 | Manajemen akun dan reset | Admin scope/Superadmin | Admin, Backend, DB, Mail | Account updated | AD-01 |
| AD-15 | Master wilayah dan UMP | Superadmin | Superadmin, Backend, DB | Master updated | Referensi profil/company |
| AD-16 | Similarity dan verifikasi perusahaan | Admin scope | Admin, Backend, DB | Verify/reject | Dapat memanggil AD-17 |
| AD-17 | Auto-replace perusahaan | Admin scope | Admin, Backend, DB | References moved | Transactional |
| AD-18 | Single LinkedIn via Apify | Admin scope | Admin, Backend, Apify, DB | Pending snapshot/error | Review AD-20 |
| AD-19 | Official/mock dan batch LinkedIn | Admin scope | Admin, Backend, Provider, DB | Result per alumni | Review AD-20 |
| AD-20 | Review/mapping/plot ulang LinkedIn | Admin scope | Admin, Backend, DB, URL | Approved/rejected | Tidak menghapus history |
| AD-21 | Completion dan audit data | Backend/admin | Backend, DB, Admin | Status audit | Dipakai dashboard |
| AD-22 | Audit log | Superadmin | Superadmin, Backend, DB | Log list | Bukti tindakan |
| AD-23 | Evaluasi publik token | Atasan | Atasan, Backend, DB | Response submitted/error | Dipicu AD-06 |
| AD-24 | Boundary Inertia/403/404 | Semua pengguna | Frontend, Backend, DB | Redirect/error valid | Berlaku lintas mutation |

Deskripsi detail semua proses berada di docs/activitydiagram.md.
