# Daftar Activity Diagram

| ID | Judul | Stakeholder utama | Swimlane | Hasil akhir | Keterkaitan |
|---|---|---|---|---|---|
| AD-01 | Login dan pengalihan dashboard | Pengguna terdaftar | Pengguna, Backend, DB | Session atau error login | Prasyarat route privat |
| AD-01A | Lupa dan reset kata sandi | Pengguna terdaftar | Pengguna, Backend, DB, Mail | Password baru atau error reset | Dipicu dari AD-01 |
| AD-01B | Perubahan kata sandi wajib | Alumni | Alumni, Backend, DB | Flag wajib ganti terhapus | Lanjutan AD-01 bila diperlukan |
| AD-02 | Isi dan update profil alumni | Alumni/admin scope | Aktor, Backend, DB | Profil tersimpan | Sinkron profile ke tracer |
| AD-03 | Ajukan perusahaan alumni | Alumni | Alumni, Backend, DB | Company pending | Masuk AD-16 |
| AD-04 | Tracer universitas | Alumni | Alumni, Backend, DB | Tracer tersimpan | Status AD-21 |
| AD-05 | Tracer prodi | Alumni | Alumni, Backend, DB | ProdiResponse | Terpisah dari AD-04 |
| AD-06 | Kirim undangan evaluasi atasan | Alumni/admin scope | Aktor, Backend, DB, Mail | Invitation sent atau error | Membuka AD-23 |
| AD-07 | Direktori dan detail alumni | Admin scope | Admin, Backend, DB | Daftar/detail alumni | Biro 3 dirinci AD-08 |
| AD-07A | Ekspor data alumni | Admin scope | Admin, Backend, DB | Excel atau ZIP | Berdiri sendiri dari AD-07 |
| AD-07B | Kirim pengingat alumni | Admin scope | Admin, Backend, Mail | Email pengingat | Berdiri sendiri dari AD-07 |
| AD-07C | Kelola data alumni terintegrasi | Admin scope/Biro 3 | Admin/Biro 3, Sistem, Database & Layanan | Detail atau hasil aksi | Merangkum AD-02, AD-07A, AD-07B, AD-18, dan AD-20 |
| AD-08 | Monitoring dan blast Biro 3 | Biro 3 | Biro 3, Backend, DB, Mail | Monitoring/communication | Read-only detail |
| AD-09 | Dashboard prodi dan fakultas | Admin Prodi/Fakultas | Admin, Backend, DB | Dashboard scoped | Memakai AD-21 |
| AD-10 | Dashboard superadmin | Superadmin | Superadmin, Backend, DB | Dashboard university | Filter tahun |
| AD-11 | Instrumen universitas | Superadmin | Superadmin, Backend, DB | Instrument updated | Dipakai AD-04 |
| AD-12 | Instrumen prodi | Superadmin/Prodi/Fakultas | Aktor, Backend, DB | Instrument scoped | Dipakai AD-05 |
| AD-13 | Instrumen evaluasi atasan | Superadmin | Superadmin, Backend, DB | Active instrument | Dipakai AD-23 |
| AD-14 | Manajemen akun | Superadmin | Superadmin, Backend, DB | Account updated | AD-01 |
| AD-14A | Kirim tautan reset akun | Superadmin | Superadmin, Backend, DB, Mail | Reset link sent | AD-01A |
| AD-15 | Master wilayah dan negara | Superadmin | Superadmin, Backend, DB | Master wilayah diperbarui | Referensi profil/company |
| AD-15B | Master UMP | Superadmin | Superadmin, Backend, DB | UMP per provinsi dan tahun tersimpan | Terpisah dari AD-15 |
| AD-16 | Penerimaan data dan rekomendasi master perusahaan | Alumni/LinkedIn process | Source, Backend, DB | Pending plus recommendations | Tidak mengubah status approval |
| AD-17A | Keputusan approval perusahaan | Admin scope | Admin, Backend, DB | Verify/edit/reject/replace decision | Membaca rekomendasi AD-16 |
| AD-17 | Auto-replace perusahaan | Admin scope | Admin, Backend, DB | References moved | Dipanggil AD-17A |
| AD-18 | Single LinkedIn via Apify | Admin scope | Admin, Backend, Apify, DB | Pending snapshot/error | Review AD-20 |
| AD-19 | Official/mock dan batch LinkedIn | Admin scope | Admin, Backend, Provider, DB | Result per alumni | Review AD-20 |
| AD-20 | Review/mapping/plot ulang LinkedIn | Admin scope | Admin, Backend, DB, URL | Approved/rejected | Tidak menghapus history |
| AD-21 | Completion dan audit data | Backend/admin | Backend, DB, Admin | Status audit | Dipakai dashboard |
| AD-22 | Audit log | Superadmin | Superadmin, Backend, DB | Log list | Bukti tindakan |
| AD-23 | Evaluasi publik token | Atasan | Atasan, Backend, DB | Response submitted/error | Dipicu AD-06 |
| AD-24 | Boundary Inertia/403/404 | Semua pengguna | Frontend, Backend, DB | Redirect/error valid | Berlaku lintas mutation |
| AD-25 | Logout dan pengakhiran sesi | Pengguna terautentikasi | Pengguna, Backend, DB | Sesi invalid dan kembali publik | Berdiri sendiri dari AD-01 |

Deskripsi detail semua proses berada di docs/activitydiagram.md.
