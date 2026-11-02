# UML Activity Diagram Keseluruhan Sistem Tracer Study UKDW

Dokumen ini adalah dokumentasi aktivitas berbasis implementasi repository, bukan rancangan fitur hipotetis. Audit dilakukan terhadap route Laravel, controller, service, model, migration, middleware, halaman Inertia/Vue, mail, view database, serta integrasi LinkedIn/Apify yang tersedia pada repository per 23 Oktober 2026.

Dokumen ini sengaja tidak membatasi jumlah diagram pada tujuh proses. Setiap diagram mewakili satu proses bisnis yang dapat ditelusuri ke implementasi. File PlantUML sumbernya berada di folder docs/uml/activity-diagrams.

## 1. Batasan dan bukti implementasi

### 1.1 Komponen yang ditemukan

| Komponen | Bukti implementasi | Catatan |
|---|---|---|
| Web route | routes/web.php | 185 route non-vendor terdaftar saat audit. |
| Backend | app/Http/Controllers, app/Services, app/Providers | Memvalidasi input, menerapkan scope, menjalankan transaksi, dan menyiapkan Inertia/JSON. |
| Frontend | resources/js/Pages, komponen Vue, Vite | Menampilkan form, tabel, dashboard, modal, dan navigasi Inertia. |
| Database | migrations, Eloquent models, database views | Data utama tersimpan di tabel relasional; status audit juga tersedia melalui view. |
| Email | app/Mail | Reset password, pengingat kuesioner, dan undangan evaluasi atasan. |
| Third party | app/Services/LinkedIn/Providers/ApifyLinkedInProvider.php dan provider lain | Integrasi LinkedIn dapat menggunakan Apify, API resmi, atau mock sesuai konfigurasi. |
| Queue/job/event | Tidak ditemukan Job, Event, Listener, atau command aplikasi yang menjalankan alur bisnis ini | Sinkronisasi Apify yang ditemukan berjalan synchronous melalui request backend; bukan job/polling/webhook. |
| Policy | Tidak ditemukan direktori app/Policies | Otorisasi dilakukan melalui middleware role, scope query, validasi controller, dan guard layanan. |

### 1.2 Notasi

- Initial: titik mulai proses.
- Action: pekerjaan aktor atau sistem.
- Decision: keputusan dengan guard eksplisit.
- Merge: penggabungan cabang alternatif.
- Fork/Join: hanya digunakan jika benar-benar ada proses paralel.
- Activity final: proses selesai sukses.
- Error final: proses berhenti karena validasi, akses, token, atau layanan eksternal gagal.
- Swimlane membedakan aktor, backend, database, mail service, dan Apify/third party.

Diagram Mermaid di bawah digunakan sebagai peta baca cepat. Detail source-of-truth dalam bentuk PlantUML tersedia pada folder UML.

## 2. Aktor dan cakupan akses

| Aktor | Cakupan dan tanggung jawab yang dibuktikan source code |
|---|---|
| Alumni | Mengelola akun sendiri, profil, perusahaan, kuesioner universitas/prodi, dan mengirim undangan evaluasi atasan. |
| Admin Prodi | Mengelola data alumni dalam user.prodi_id, instrumen prodi sendiri, perusahaan dalam scope prodi, akun alumni prodi, dan sinkronisasi LinkedIn dalam scope prodi. |
| Admin Fakultas | Mengelola data pada seluruh prodi dengan prodi.fakultas_id yang sama, instrumen semua prodi di fakultas, perusahaan, akun, dan LinkedIn pada scope fakultas. |
| Biro 3 | Monitoring universitas, direktori, detail baca, ekspor, pengingat satuan, blast email, dan analisis. Method perubahan profilnya menolak perubahan; tidak memiliki route verifikasi perusahaan atau LinkedIn. |
| Superadmin | Cakupan universitas: dashboard, seluruh alumni, seluruh kuesioner, master perusahaan, verifikasi, wilayah/UMP, akun, evaluasi atasan, log, dan LinkedIn. |
| Backend | Auth, role middleware, validasi, scope query, normalisasi, transaksi, perhitungan kelengkapan, mapping, logging, dan respons Inertia/JSON. |
| Database | Menyimpan akun, referensi akademik, biodata, tracer, kuesioner, perusahaan, evaluasi, audit, LinkedIn, serta view status. |
| Mail service | Mengirim reset password, pengingat kuesioner, dan undangan evaluasi atasan. |
| Apify/third party | Scraping/enrichment profil LinkedIn bila provider Apify dipilih; hasil dikembalikan ke backend dalam request yang sama. |

Catatan penting: RoleMiddleware menangani role; scope data kemudian ditegakkan di controller/service dengan prodi_id, fakultas_id, atau akses universitas. Tidak ada bukti bahwa Biro 3 dapat mengubah biodata, memverifikasi perusahaan, atau menjalankan LinkedIn sync.

## 3. Inventarisasi modul dan fitur

| Modul | Fitur yang diaudit | Pemicu dan aktor | Backend dan data utama | Diagram |
|---|---|---|---|---|
| Autentikasi | Login, fallback password tanggal lahir, logout | Tamu/Alumni/Admin | LoginController, User, Biodata, LogActivity | AD-01 |
| Autentikasi | Lupa/reset password | Pengguna tamu | LupaKataSandiController, ResetPasswordMail, password_reset_tokens | AD-01 |
| Autentikasi | Ganti password wajib | Alumni | UbahKataSandiController, CheckMustChangePassword, users | AD-01 |
| Profil | Isi/perbarui biodata | Alumni; admin scope | SimpanProfilController, AdminAlumniProfileService, Biodata, Atasan, Perusahaan | AD-02 |
| Perusahaan | Ajukan perusahaan baru | Alumni | PerusahaanController, Perusahaan, Biodata | AD-03 |
| Tracer universitas | Ambil instrumen, simpan jawaban parsial/akhir, branching | Alumni | KuesionerController, SimpanJawabanController, Tracer, KuesionerSyncService | AD-04 |
| Tracer prodi | Isi dan simpan jawaban prodi | Alumni | KuesionerProdiController, ProdiResponse | AD-05 |
| Evaluasi atasan | Kirim undangan, isi evaluasi berbasis token | Alumni/Atasan | EvaluasiAtasanController, Atasan, EvaluasiAtasan, ResponEvaluasiAtasan | AD-06 |
| Direktori | Cari/filter/detail/update profile/ekspor/pengingat | Admin Prodi/Fakultas/Superadmin; Biro 3 baca | AlumniDirectoryQueryService, exporter, mail | AD-07 |
| Monitoring Biro 3 | Direktori, ekspor ZIP, blast, detail baca | Biro 3 | DashboardBiro3Controller, DaftarAlumniBiro3Controller | AD-08 |
| Dashboard | Ringkasan berdasarkan tahun dan scope | Semua role administrator sesuai route | Controller dashboard, audit views, KelengkapanTracerService | AD-09, AD-10 |
| Instrumen universitas | CRUD section/question/option, reorder, toggle | Superadmin | question management controllers, Kuesioner, KelompokPertanyaan, RefSubpertanyaan | AD-11 |
| Instrumen prodi | CRUD section/question/option, reorder | Superadmin/Prodi/Fakultas sesuai scope | ProdiQuestion*, ProdiResponse | AD-12 |
| Instrumen atasan | CRUD, aktif/nonaktif, reorder | Superadmin | PertanyaanEvaluasiAtasan, ResponEvaluasiAtasan | AD-13 |
| Akun | Edit akun, reset default, notifikasi | Superadmin; admin Prodi/Fakultas scope | account controllers, User, Biodata, mail | AD-14 |
| Wilayah/UMP | CRUD provinsi/kabupaten/UMP, copy tahun | Superadmin | Propinsi, Kabupaten, Ump | AD-15 |
| Perusahaan | Rekomendasi, verifikasi, edit, tolak | Superadmin/Prodi/Fakultas sesuai scope | PerusahaanVerificationService, Perusahaan, LogActivity | AD-16 |
| Perusahaan | Auto-replace perusahaan pending dengan master | Superadmin/Prodi/Fakultas sesuai scope | transaksi Perusahaan, Biodata, tracer, evaluasi | AD-17 |
| LinkedIn | Sinkronisasi satu alumni via Apify | Superadmin/Prodi/Fakultas scope | LinkedInSyncController, ApifyLinkedInProvider, linkedin_sync_results | AD-18 |
| LinkedIn | Provider API resmi/mock dan batch | Superadmin/Prodi/Fakultas scope | LinkedInProfileService, provider mapper | AD-19 |
| LinkedIn | Review, approve, plot ulang, reject, history | Superadmin/Prodi/Fakultas scope | LinkedInProfileMapper, AlumniLinkedinPlot, LinkedinSyncResult, Biodata | AD-20 |
| Audit | Status kelengkapan profile/tracer dan audit rekap | Backend/dashboard | KelengkapanTracerService, v_*_status, v_alumni_audit_rekap | AD-21 |
| Log | Filter dan lihat audit log | Superadmin | LogAktivitasSuperAdminController, LogActivity | AD-22 |
| Evaluasi publik | Validasi token, isi, simpan respons | Atasan eksternal | EvaluasiAtasanController, throttle, database | AD-23 |
| Respons HTTP | Redirect Inertia, JSON fetch, 403/404 | Semua request terautentikasi | middleware dan controller response negotiation | AD-24 |

## 4. Peta route dan kontrol akses

### 4.1 Route publik dan bersama

| Route group | Controller | Akses |
|---|---|---|
| /, /login, /forgot-password, /reset-password/* | Tamu dan autentikasi | Tamu |
| /evaluasi-atasan, /evaluasi-atasan/{token} | EvaluasiAtasanController | Publik dengan token; GET/POST diberi throttle |
| /logout, /change-password | Otentikasi | Pengguna terautentikasi |
| /alumni/company, /alumni/kirim-email-atasan | Controller alumni/shared | Role yang diizinkan dan scope alumni |

### 4.2 Route role utama

| Prefix | Role | Fitur |
|---|---|---|
| /alumni | Alumni | Dashboard, profil, tracer universitas, tracer prodi |
| /prodi | Admin Prodi | Dashboard, direktori, instrumen prodi, perusahaan, akun, LinkedIn |
| /fakultas | Admin Fakultas | Dashboard, direktori scope fakultas, instrumen, perusahaan, akun, LinkedIn |
| /biro3 | Biro 3 | Dashboard, direktori monitoring, ekspor, reminder, blast email |
| /superadmin | Superadmin | Dashboard dan seluruh konfigurasi/master/validasi universitas |

### 4.3 Inertia dan JSON

Request halaman memakai Inertia::render. Mutation normalnya mengembalikan redirect dengan flash/error untuk request Inertia. LinkedInSyncController juga mendukung JSON saat request fetch/API; response plain JSON tidak boleh dikirim ke request Inertia karena akan menghasilkan error â€œAll Inertia requests must receive a valid Inertia responseâ€.

## 5. Diagram aktivitas

### AD-01 â€” Autentikasi, pemulihan, dan perubahan password

**Swimlane:** Pengguna/Tamu, Backend, Database, Mail service.
**Pemicu:** membuka login, lupa password, atau diwajibkan mengganti password.
**Hasil:** session aktif, alumni diarahkan ke perubahan password wajib, token reset dikirim, password berhasil dipulihkan, logout, atau akses ditolak.

Alur lengkap:

1. Tamu membuka halaman login. Backend merender form login melalui Inertia.
2. Pengguna mengirim username dan password. Backend memvalidasi kedua field sebagai input wajib.
3. Backend mencoba kredensial normal melalui username dan password yang tersimpan.
4. Jika berhasil, session diregenerasi untuk mencegah session fixation.
5. Jika pengguna adalah alumni dan `must_change_password = true`, backend mengarahkan ke `/change-password`. Jika tidak, backend mengarahkan ke dashboard sesuai role.
6. Jika login normal gagal, backend hanya mencoba fallback untuk akun alumni yang masih wajib mengganti password atau masih memakai hash password legacy.
7. Fallback mengambil tanggal lahir dari biodata atau `data_akademik`, menormalisasi input menjadi angka, lalu mencocokkannya dengan format `ddmmyyyy`.
8. Jika fallback cocok, password di-hash ulang, `must_change_password` tetap/diubah menjadi `true`, alumni diloginkan, session diregenerasi, lalu diarahkan ke `/change-password`.
9. Jika semua cara gagal, backend mengembalikan error login dan hanya mempertahankan input username.
10. Pada alur lupa password, tamu mengirim NIM/username atau email. Backend mencari kecocokan pada `users.username`, `users.email`, atau `biodata.email_pribadi`.
11. Backend selalu mengembalikan pesan generik agar keberadaan akun tidak terbuka. Jika akun dan email valid, backend membuat token acak, menyimpan hash token pada `password_reset_tokens` bersama waktu pembuatan, lalu mengirim email reset.
12. Pengguna membuka link reset. Backend menampilkan form reset dengan token mentah dan email sebagai konteks form; token belum dianggap valid sebelum POST diverifikasi.
13. Saat form reset dikirim, backend memvalidasi token, email, password minimal 12 karakter, dan konfirmasi password.
14. Backend mencari record token, menolak token yang tidak ada atau lebih dari 60 menit, lalu memeriksa hash token.
15. Jika valid, backend mencari user berdasarkan email akun atau email pribadi biodata, menyimpan password baru, menghapus `must_change_password`, dan menghapus token reset.
16. Pengguna diarahkan ke login dan harus masuk menggunakan password baru.
17. Pada setiap route terautentikasi, middleware memeriksa alumni yang masih memiliki `must_change_password = true`. Semua route selain `/change-password` dan logout diarahkan kembali ke form perubahan password.
18. Form perubahan password hanya dapat digunakan alumni yang memang memiliki flag tersebut. Password baru divalidasi minimal 12 karakter dan konfirmasi, kemudian disimpan, flag diubah menjadi `false`, dan pengguna diarahkan ke dashboard sesuai role.
19. Logout menjalankan `Auth::logout`, menginvalidasi session, dan meregenerasi CSRF token.

~~~mermaid
flowchart TD
  A((Mulai)) --> L[Pilih Login atau Lupa Password]
  L --> Q{Alur login?}
  Q -- Ya --> U[Kirim username dan password]
  U --> B[Validasi dan Auth attempt]
  B --> D{Berhasil?}
  D -- Ya --> R{Alumni wajib ganti password?}
  D -- Tidak --> F{Fallback alumni dan tanggal lahir cocok?}
  F -- Ya --> M[Hash ulang password dan set must_change_password]
  F -- Tidak --> E[Tampilkan error login]
  M --> R
  R -- Ya --> C[Form ganti password wajib]
  R -- Tidak --> S[Redirect dashboard sesuai role]
  C --> V[Validasi minimal 12 karakter dan konfirmasi]
  V --> P[Simpan password baru dan hapus flag]
  P --> S
  Q -- Tidak --> FP[Kirim username/NIM atau email]
  FP --> FE[Cari user, tetapi tampilkan status generik]
  FE --> FT[Buat hash token reset 60 menit dan kirim email]
  FT --> RF[Buka link reset]
  RF --> RV[Validasi token, expiry, email, password]
  RV --> RD{Token valid?}
  RD -- Ya --> RP[Simpan password, hapus flag dan token]
  RD -- Tidak --> RE[Error reset]
  RP --> LG[Login dengan password baru]
  E --> Z((Selesai error))
  RE --> Z
  S --> Z2((Selesai))
  LG --> Z2
~~~

### AD-02 â€” Pengisian dan pembaruan profil alumni

**Swimlane:** Alumni/Admin scope, Backend, Database, Mail service.
**Route:** GET/POST /alumni/profile, admin detail update, shared /alumni/company.
**Hasil:** biodata, pekerjaan, perusahaan, atasan, foto, dan sinkronisasi jawaban profil tersimpan.

1. Backend memuat biodata, data akademik, yudisium, perusahaan, atasan, dan metadata profil sesuai kebutuhan role.
2. Pengguna mengirim profil; backend memvalidasi array jawaban, foto, email, telepon, URL, tanggal, gaji, dan field relevan.
3. Data bidang pekerjaan dinormalisasi; nilai â€œLainnyaâ€ menggunakan input custom.
4. Aturan posisi eksklusif diterapkan: pekerja mengosongkan data wiraswasta, wiraswasta mengosongkan jabatan, melanjutkan pendidikan mengosongkan dua data tersebut.
5. Gaji dinormalisasi sebagai integer.
6. Perusahaan baru dibuat dengan status menunggu verifikasi; perusahaan terverifikasi tidak ditimpa sembarangan.
7. Relasi atasan dipertahankan saat form parsial agar data tidak hilang.
8. Email biodata disinkronkan ke user, activity log ditulis, dan KuesionerSyncService memperbarui jawaban profile-managed.
9. Jika input invalid, tidak ada perubahan sukses; backend mengembalikan validasi melalui respons Inertia.

#### Detail proses profil

1. Pertanyaan profil dinamis berasal dari `ref_subpertanyaan2021` dengan `tampil_di = profile/profil`; pilihan berasal dari `ref_subpertanyaan_detil`. Field akademik yang dikunci tetap dibaca dari sumber akademik atau database view.
2. Alumni mengisi identitas, kontak, domisili, status pekerjaan, bidang/divisi, perusahaan, studi lanjut, atasan, dan pertanyaan profil yang berlaku. Untuk dropdown `Lainnya`, frontend mengirim pilihan resmi dan teks custom.
3. Backend memvalidasi ulang question id, tipe pertanyaan, pilihan, foto, email, nomor telepon, URL, tanggal, tahun, gaji, relasi wilayah/perusahaan, dan data atasan. Nilai dari frontend tidak langsung dipercaya.
4. Nilai `Lainnya` disimpan secara terstruktur sebagai pilihan resmi plus custom value pada `answer` dan/atau `answer_json`, sehingga input baru tetap dapat dikelompokkan sebagai `Lainnya`.
5. Status pekerjaan menentukan field yang relevan. Normalisasi tidak boleh mengosongkan data lama hanya karena field tersebut tidak dikirim pada form parsial.
6. Gaji dibersihkan dari pemisah mata uang menjadi integer. Nilai ini menjadi sumber sinkronisasi pertanyaan tracer yang dikelola profil, termasuk take-home pay jika aturan instrumen memetakannya ke gaji.
7. Perusahaan dicari berdasarkan identitas yang dinormalisasi. Jika belum ada, backend membuat perusahaan berstatus `Menunggu Verifikasi`; perusahaan terverifikasi tidak ditimpa oleh input alumni tanpa proses penggantian yang sah.
8. Data atasan di-upsert hanya untuk field yang dikirim. Field yang tidak ada pada request mempertahankan data lama agar penyimpanan tab lain tidak menghilangkan atasan.
9. Setelah biodata, relasi perusahaan, atasan, foto, email user, dan log tersimpan, `KuesionerSyncService` meng-upsert jawaban profile-managed ke `tracer` berdasarkan `biodata_id` dan `question_id`.

~~~mermaid
flowchart TD
  A((Mulai)) --> L[Backend memuat profil dan referensi]
  L --> U[Alumni/admin mengisi form profil dan pertanyaan dinamis]
  U --> V{Validasi struktur, tipe, format, dan scope?}
  V -- Tidak --> E[Tampilkan validation error Inertia]
  V -- Ya --> N[Normalisasi bidang kerja, Lainnya, gaji, posisi, dan email]
  N --> C{Perusahaan sudah ada?}
  C -- Tidak --> CP[Buat perusahaan Menunggu Verifikasi]
  C -- Ya --> K[Pertahankan status perusahaan]
  CP --> A[Upsert atasan tanpa mengosongkan field lama]
  K --> A
  A --> T[Simpan biodata, foto, relasi, dan log]
  T --> Q[Sync profile-managed ke tracer dengan question_id]
  Q --> R[Redirect dengan flash sukses]
  E --> X((Selesai error))
  R --> Y((Selesai))
~~~

### AD-03 â€” Pengajuan perusahaan oleh alumni

**Swimlane:** Alumni, Backend, Database.
**Route:** shared POST /alumni/company.
**Hasil:** perusahaan pending tersedia untuk dipilih atau error validasi.

1. Alumni mengirim nama, alamat/lokasi, sektor, dan field perusahaan.
2. Backend memeriksa autentikasi, role yang diperbolehkan, validasi dan duplikasi dasar.
3. Data domestic/international dinormalisasi.
4. Backend menyimpan perusahaan baru berstatus Menunggu Verifikasi, mengaitkan creator/alumni sesuai implementasi, lalu mencatat log.
5. Perusahaan yang sudah ada dapat dipakai tanpa membuat data baru jika alur form memilihnya.

### AD-04 â€” Pengisian tracer study universitas

**Swimlane:** Alumni, Backend, Database.
**Route:** GET/POST /alumni/kuesioner.
**Hasil:** jawaban tersimpan parsial atau final, dengan question_id dan answer_json bila relevan.

1. Backend mengambil section aktif, pertanyaan, opsi, jawaban tersimpan, dan data profil.
2. Jawaban profile-managed disinkronkan dari biodata.
3. Alumni mengisi satu atau beberapa halaman dan dapat menyimpan sebagian.
4. Backend memvalidasi bentuk jawaban, pilihan yang tersedia, multi-choice, text, angka, tanggal, dan custom Lainnya.
5. Backend mengevaluasi branching: F8 memengaruhi pertanyaan studi/kerja; F504 memengaruhi F506 versus F502/F505.
6. Jawaban disimpan/update berdasarkan alumni dan pertanyaan, termasuk question_id, answer, answer_json, dan metadata.
7. KelengkapanTracerService menghitung mandatory/optional dengan pertanyaan tidak berlaku tidak dihitung sebagai kekurangan.
8. Request Inertia menerima redirect/flash, bukan plain JSON.

#### Detail percabangan kuesioner universitas

1. Halaman hanya memuat kuesioner aktif, section aktif, pertanyaan yang bukan bagian form profil, opsi/detail pertanyaan, jawaban `tracer`, dan data autofill dari database view. Jawaban profile-managed disinkronkan dari biodata.
2. Alumni dapat menyimpan progres parsial. Backend tetap memvalidasi tipe jawaban, opsi yang berasal dari pertanyaan tersebut, format angka/tanggal, multi-choice, serta custom `Lainnya`.
3. IF F8 (status setelah lulus) diterapkan oleh frontend untuk tampilan dan dihitung ulang oleh backend untuk audit:
   - **Melanjutkan Pendidikan (4):** lewati F10, F3, F4, F504, F502, F505, F506, F6, F7, F7A, F14, F15, dan F16. Pertanyaan studi lanjut F18 dan turunannya tetap berlaku.
   - **Belum Memungkinkan Bekerja (2):** lewati F18, F12, F18A, F18B, F18C, F18D, F3, F4, F504, F502, F505, F506, F6, F7, F7A, F14, F15, dan F16. F10 tetap berlaku.
   - **Sedang Mencari Kerja (5):** lewati F18, F12, F18A, F18B, F18C, F18D, F504, F502, F505, F506, F14, F15, dan F16. F10 tetap berlaku.
   - **Bekerja/Wiraswasta (status selain tiga kondisi di atas, termasuk 1 dan 3):** lewati F10 serta F18, F12, F18A, F18B, F18C, dan F18D.
4. IF F504 diterapkan jika pertanyaan masih berlaku: **Ya (1)** melewati F506; **Tidak (2)** melewati F502 dan F505.
5. Backend juga membaca `jump_to` pada opsi. Jika opsi menunjuk ke pertanyaan berikutnya, semua pertanyaan di antara pertanyaan aktif dan target ditandai skipped.
6. Jawaban yang lolos validasi di-upsert berdasarkan `biodata_id` dan `question_id`. Jawaban terstruktur disimpan di `answer_json`, sedangkan `answer`/`answer_text` dipakai sebagai representasi baca.
7. `KelengkapanTracerService` mengambil pertanyaan wajib dan opsional, mengecualikan profile-managed, lalu tidak menghitung pertanyaan skipped sebagai kekurangan. Pertanyaan wajib yang masih berlaku tetapi kosong tetap dihitung belum lengkap.

~~~mermaid
flowchart TD
  A((Mulai)) --> L[Backend memuat instrumen aktif dan jawaban]
  L --> P[Prefill profile-managed, autofill, dan jawaban tersimpan]
  P --> U[Alumni mengisi atau menyimpan progres]
  U --> V{Format, opsi, dan custom Lainnya valid?}
  V -- Tidak --> E[Kembalikan validation error]
  V -- Ya --> S[Upsert tracer dengan question_id dan answer_json]
  S --> F8{F8 terisi?}
  F8 -- Ya --> B8[Tentukan set skipped berdasarkan status F8]
  F8 -- Tidak --> B8
  B8 --> F504{F504 masih berlaku dan terisi?}
  F504 -- Ya --> B504[Tentukan F506 atau F502/F505 skipped]
  F504 -- Tidak --> J[Evaluasi aturan jump_to]
  B504 --> J
  J --> C[Hitung mandatory/optional; skipped bukan kekurangan]
  C --> R[Redirect/flash Inertia]
  E --> X((Selesai error))
  R --> Y((Selesai))
~~~

### AD-05 â€” Pengisian tracer study prodi

**Swimlane:** Alumni, Backend, Database.
**Route:** GET/POST /alumni/kuesioner-prodi.
**Hasil:** ProdiResponse dibuat atau diperbarui sesuai biodata_id dan prodi_question_id.

1. Backend memuat section/question/option sesuai prodi alumni.
2. Alumni mengisi text, textarea, number, date, choice, multi-choice, dropdown, atau input custom.
3. Backend menormalisasi jawaban dan memvalidasi opsi/format.
4. Response di-upsert dengan kunci alumni-question; nilai teks/json disimpan.
5. Status kelengkapan prodi dihitung terpisah dari kelengkapan universitas.

#### Detail proses kuesioner prodi

1. Backend mengambil `prodi_id` dari biodata alumni. Jika belum ada prodi, halaman mengembalikan respons Inertia dengan instrumen kosong dan pesan; pertanyaan prodi lain tidak boleh diakses.
2. Section, pertanyaan, opsi, dan respons hanya diambil untuk prodi alumni. Identitas yang ditandai otomatis dapat diprefill dari `v_alumni_kuesioner_autofill` atau biodata.
3. Backend memeriksa question id yang dikirim benar-benar milik prodi alumni, menormalisasi jawaban array/terstruktur, memeriksa opsi dan format, serta menyimpan pilihan custom sebagai `answer_json` bila diperlukan.
4. Jawaban terisi di-upsert dengan kunci `biodata_id + prodi_question_id`. Jika jawaban kosong dan bukan autofill identitas, respons lama dihapus agar persentase tidak salah.
5. Status kelengkapan prodi dihitung terpisah dari kuesioner universitas. Request sukses memakai redirect/flash Inertia; input invalid tidak disimpan dan tidak menghasilkan plain JSON.

### AD-06 â€” Undangan dan evaluasi atasan

**Swimlane:** Alumni/Admin scope, Backend, Database, Mail service, Atasan/Pengguna Lulusan.
**Route:** POST /alumni/kirim-email-atasan, GET/POST public /evaluasi-atasan/{token}.
**Hasil:** data atasan tersimpan saat profil disimpan; evaluasi dan token baru dibuat ketika alumni meminta pengiriman undangan, lalu respons pengguna lulusan tersimpan satu kali.

1. Alumni mengisi data atasan: nama, email, telepon, jabatan, dan perusahaan.
2. Saat profil disimpan, backend hanya menyimpan atau memperbarui data Atasan pada biodata. Penyimpanan profil tidak membuat EvaluasiAtasan dan tidak membuat token.
3. Penyimpanan profil tidak otomatis mengirim email. Email hanya dikirim melalui tombol Kirim Email ke Atasan.
4. Saat tombol kirim dipilih, backend menentukan biodata target. Alumni mengirim untuk dirinya sendiri; admin dapat mengirim untuk alumni dalam scope yang diizinkan.
5. Backend memvalidasi email dan menyinkronkan email ke Atasan.
6. Jika EvaluasiAtasan belum ada, backend membuatnya berdasarkan biodata_id dan membuat token acak 40 karakter. Jika sudah ada, token yang ada dipertahankan agar tombol kirim ulang tidak membuat evaluasi ganda.
7. Mail service mengirim UndanganEvaluasiAtasanMail berisi URL /evaluasi-atasan/{token}. Pengiriman dipanggil synchronous melalui Mail::send.
8. Atasan membuka URL token tanpa login. GET dibatasi throttle 30 request per menit.
9. Backend mencari token dan menolak jika tidak ditemukan atau evaluasi sudah is_submitted. Tidak ada expiry token; token menjadi tidak berlaku setelah submitted.
10. Jika valid, backend memuat data alumni, perusahaan, atasan, pertanyaan evaluasi yang aktif, dan jawaban existing, lalu merender form publik.
11. Atasan mengirim informasi perusahaan, data atasan, metadata jumlah alumni/gaji pertama, seluruh jawaban aktif, dan catatan. POST dibatasi throttle 10 request per menit.
12. Backend memvalidasi bahwa kode jawaban sama persis dengan seluruh pertanyaan aktif. Jawaban yang kurang atau memiliki kode pertanyaan asing ditolak.
13. Dalam transaksi, backend mengunci EvaluasiAtasan, menolak pengiriman kedua, membuat ResponEvaluasiAtasan dengan pertanyaan_id, nilai, dan catatan, memperbarui atau membuat Atasan, lalu menautkan atasan ke biodata jika perlu.
14. Backend memperbarui metadata evaluasi, mengubah is_submitted menjadi true, dan mengisi submitted_at.
15. Admin membaca hasil melalui payload audit tanpa token akses. Token tidak seharusnya ditampilkan pada halaman admin yang hanya membutuhkan status dan respons.

Catatan desain: pembuatan EvaluasiAtasan dan token ditempatkan pada aksi Kirim Email, bukan pada aksi Simpan Profil. Jika evaluasi sudah submitted, endpoint pengiriman menolak pengiriman ulang karena token sudah hangus. Field informasi perusahaan divalidasi dan disimpan ke record perusahaan pending; record perusahaan yang sudah Terverifikasi tidak ditimpa oleh input publik.

### AD-07 â€” Direktori alumni, detail, reminder, dan ekspor

**Swimlane:** Admin Prodi/Admin Fakultas/Superadmin/Biro 3, Backend, Database, Mail service.
**Hasil:** daftar terfilter, detail, pembaruan berotorisasi, file ekspor, atau email pengingat.

1. Aktor memilih search, tahun, target/semester, prodi, atau status.
2. AlumniDirectoryQueryService membangun query dengan scope role.
3. Backend mengembalikan index/detail melalui Inertia.
4. Admin Prodi/Fakultas/Superadmin dapat update sesuai scope; Biro 3 hanya melihat detail.
5. Ekspor individual menggunakan AlumniTracerExcelExporter; ekspor ZIP menggunakan controller role terkait.
6. Reminder satuan dikirim oleh admin scope; data tidak ditemukan atau di luar scope menghasilkan 403/404 sesuai guard.

### AD-08 â€” Monitoring dan komunikasi Biro 3

**Swimlane:** Biro 3, Backend, Database, Mail service.
**Route:** /biro3/dashboard, /biro3/alumni, export, blast, detail.
**Hasil:** monitoring universitas, ekspor, atau pengiriman komunikasi berkelanjutan.

1. Biro 3 membuka dashboard/direktori universitas.
2. Backend menghitung populasi, response, pertanyaan, prodi, LinkedIn, dan item monitoring.
3. Biro 3 dapat melihat detail dan ekspor.
4. Blast email memilih target alumni tidak lengkap; backend mengirim satu per satu, mencatat error per penerima, dan melanjutkan penerima lain.
5. Method update profil Biro 3 mengembalikan warning read-only; tidak ada perubahan biodata.
6. Tidak ada route Biro 3 untuk verifikasi perusahaan atau LinkedIn sync.

### AD-09 â€” Dashboard Admin Prodi dan Fakultas

**Swimlane:** Admin Prodi/Admin Fakultas, Backend, Database.
**Hasil:** dashboard terfilter sesuai scope, tanpa akses data di luar prodi/fakultas.

1. Admin membuka dashboard.
2. Backend membaca role dan scope user.
3. Prodi memakai user.prodi_id; Fakultas membentuk daftar prodi dari user.fakultas_id.
4. Backend menghitung populasi, response rate, kelengkapan universitas/prodi, pending company, LinkedIn, dan ringkasan instrumen.
5. Database view audit dan query agregasi menyediakan data; halaman menerima prop Inertia.
6. Akses dengan scope tidak cocok ditolak, bukan dikembalikan sebagai data universitas penuh.

### AD-10 â€” Dashboard Superadmin universitas

**Swimlane:** Superadmin, Backend, Database.
**Route:** /superadmin/dashboard.
**Hasil:** ringkasan populasi, response rate, kelengkapan, tindak lanjut, dan filter tahun kelulusan.

1. Superadmin memilih tahun; default mengikuti tahun berjalan jika tersedia.
2. Backend memfilter populasi alumni dan metrik berbasis tahun; antrean perusahaan ditampilkan sebagai tindak lanjut lintas tahun jika implementasi route memang demikian.
3. Response rate memakai jumlah respon dibanding populasi pada scope/tahun.
4. Kelengkapan profil dan tracer universitas dihitung melalui status audit; kuesioner prodi tidak dimasukkan pada kartu gabungan universitas.
5. Pertanyaan yang tidak berlaku karena branching tidak dihitung sebagai kekurangan.
6. Backend mengembalikan data Inertia; kartu dashboard tidak mengubah data.

~~~mermaid
flowchart TD
  A((Mulai)) --> U[Superadmin memilih tahun]
  U --> B[Backend validasi filter]
  B --> D[Query populasi dan status audit]
  D --> C[Hitung response rate dan kelengkapan]
  C --> P{Ada antrean perusahaan?}
  P -- Ya --> Q[Tampilkan tindak lanjut lintas tahun]
  P -- Tidak --> N[Tampilkan status kosong]
  Q --> R[Render dashboard Inertia]
  N --> R
  R --> Z((Selesai))
~~~

### AD-11 â€” Pengelolaan instrumen kuesioner universitas

**Swimlane:** Superadmin, Backend, Database.
**Route:** /superadmin/pertanyaan, /superadmin/sections, /superadmin/kuesioner, option CRUD/reorder/toggle.
**Hasil:** section/question/option aktif dan urut, atau perubahan ditolak.

1. Superadmin membuka daftar instrumen.
2. Backend memuat section, question, option, status aktif, tipe, urutan, dan konfigurasi branching.
3. Superadmin membuat/mengubah/menghapus question, option, section, mengurutkan, atau toggle kuesioner.
4. QuestionDefaultOptionsService membuat opsi default hanya bila belum ada sesuai tipe.
5. Backend memvalidasi kode unik, type, section, option, jump_to, dan relasi.
6. Database menyimpan perubahan; jawaban tracer yang sudah ada tidak boleh diasumsikan ikut terhapus.

### AD-12 â€” Pengelolaan instrumen kuesioner prodi

**Swimlane:** Superadmin/Admin Prodi/Admin Fakultas, Backend, Database.
**Hasil:** instrumen prodi terkelola dalam scope yang sesuai.

1. Superadmin memilih prodi; Admin Prodi otomatis terbatas ke prodi sendiri; Admin Fakultas memilih prodi dalam fakultas.
2. Backend memverifikasi scope sebelum membaca atau bermutasi.
3. Aktor mengelola section, question, option, urutan, dan default option.
4. Response existing disimpan terpisah melalui ProdiResponse.
5. Scope gagal menghasilkan 403; resource tidak ada menghasilkan 404 atau validasi sesuai route.

### AD-13 â€” Pengelolaan instrumen evaluasi atasan

**Swimlane:** Superadmin, Backend, Database.
**Route:** controller pengelolaan pertanyaan evaluasi atasan.
**Hasil:** daftar butir evaluasi aktif, terurut, dan tersedia untuk form publik.

1. Superadmin membuka instrumen.
2. Backend mengambil PertanyaanEvaluasiAtasan.
3. Superadmin CRUD, toggle aktif/nonaktif, dan reorder.
4. Backend memvalidasi kode, label, dan urutan.
5. Form publik hanya membaca pertanyaan aktif; respons lama tetap berada di ResponEvaluasiAtasan.

### AD-14 â€” Pengelolaan akun dan reset password

**Swimlane:** Superadmin/Admin Prodi/Admin Fakultas, Backend, Database, Mail service.
**Hasil:** akun terbarui atau password default dikirim.

1. Aktor membuka manajemen akun.
2. Backend menerapkan scope: universitas, prodi, atau fakultas.
3. Aktor memperbarui role, nama, email, username, prodi/fakultas sesuai hak route.
4. Backend memvalidasi keunikan identitas dan menulis log.
5. Reset default hanya boleh untuk alumni dalam scope; password/flag diperbarui dan notifikasi dikirim bila diminta.
6. User di luar scope tidak boleh diubah walaupun ID diketahui.

### AD-15 â€” Master wilayah dan UMP

**Swimlane:** Superadmin, Backend, Database.
**Route:** /superadmin/wilayah-ump dan CRUD turunannya.
**Hasil:** provinsi, kabupaten/kota, dan UMP per tahun tersimpan konsisten.

1. Superadmin melihat ringkasan jumlah provinsi, kabupaten/kota, ketetapan UMP, dan rata-rata.
2. CRUD provinsi dan kabupaten divalidasi terhadap kode/nama dan relasi parent.
3. Perubahan kode provinsi memperbarui relasi UMP sesuai migration/controller.
4. Penghapusan provinsi menghapus UMP terkait sesuai aturan implementasi.
5. UMP disimpan per provinsi dan tahun; copy-year menyalin data sesuai guard agar tidak menimpa data tanpa aturan.

### AD-16 â€” Rekomendasi dan verifikasi perusahaan

**Swimlane:** Alumni atau proses LinkedIn, Backend, Database.
**Route:** POST shared /alumni/company dan proses pembuatan perusahaan saat approval LinkedIn; rekomendasi dihitung saat index perusahaan dibuka.
**Hasil:** record perusahaan pending tersimpan dan daftar rekomendasi master terverifikasi tersedia. Tahap ini belum melakukan approval atau replace.

1. Data perusahaan dapat berasal dari dua sumber: alumni mengajukan perusahaan baru, atau hasil approval LinkedIn membuat/menetapkan perusahaan kerja yang harus masuk antrean verifikasi.
2. Backend menyimpan atau menemukan record perusahaan dan memastikan data yang diajukan berada pada status Menunggu Verifikasi sesuai alur sumbernya.
3. Saat halaman antrean dibuka, controller mengambil perusahaan pending sesuai scope role. RoleMiddleware sudah menangani autentikasi dan role route; controller menerapkan scope query.
4. PerusahaanVerificationService membersihkan nama perusahaan:
   - menghapus isi tanda kurung;
   - menghapus tanda baca;
   - mengubah huruf menjadi lowercase;
   - menghapus stopword badan usaha, cabang, unit, dan kata umum.
5. Backend membentuk token kata dan akronim dari nama pending.
6. Backend mengambil seluruh master perusahaan berstatus Terverifikasi. Master terverifikasi tetap dapat menjadi rekomendasi, termasuk jika nama bersihnya sama persis dengan pengajuan pending.
7. Untuk setiap kandidat master, backend menghitung similarity:
   - exact match nama bersih: skor 95;
   - akronim/singkatan: skor 88;
   - substring/containment: skor 75-90;
   - akronim parsial: skor 70-80;
   - overlap token: skor 50-75 jika minimal sepertiga token cocok dan bukan hanya kata industri generik;
   - similar_text: skor persentase kemiripan huruf jika minimal 60%.
8. Backend menambahkan geographic score jika lokasi sama:
   - kota/kabupaten sama: +20;
   - provinsi sama: +10;
   - bonus hanya berlaku jika skor nama minimal 35.
9. Kandidat diterima jika total skor minimal 45, atau kota sama dengan skor nama minimal 30.
10. Kandidat diurutkan berdasarkan skor tertinggi dan controller menempelkan rekomendasi ke setiap item antrean. Saat ini controller meminta maksimal lima rekomendasi.
11. Rekomendasi ini hanya hasil perhitungan untuk ditampilkan. Tidak ada pilihan admin dan tidak ada perubahan status pada tahap ini. Keputusan admin dijelaskan pada AD-17A.

~~~mermaid
flowchart TD
  A[Alumni ajukan perusahaan] --> B[Backend simpan perusahaan pending]
  L[Approval LinkedIn menghasilkan perusahaan kerja] --> B
  B --> DB[(Database: status Menunggu Verifikasi)]
  DB --> I[Controller membuka antrean sesuai scope]
  I --> N[Normalisasi nama, token, akronim, dan lokasi]
  N --> M[Ambil master berstatus Terverifikasi]
  M --> S[Hitung similarity nama]
  S --> G[Tambahkan geographic score]
  G --> T{Memenuhi threshold?}
  T -- Tidak --> E[Tidak ada rekomendasi kuat]
  T -- Ya --> R[Urutkan dan tampilkan maksimal lima rekomendasi]
  E --> Z((Selesai rekomendasi))
  R --> Z
~~~

### AD-17 â€” Auto-replace perusahaan pending

**Swimlane:** Superadmin/Admin Prodi/Admin Fakultas, Backend, Database.
**Hasil:** seluruh referensi perusahaan pending dipindahkan ke master terpilih, pending dihapus, dan audit tercatat.

1. Aktor memilih perusahaan pending dan master rekomendasi.
2. Backend memeriksa scope, keberadaan, status, dan target berbeda.
3. Dalam transaksi, biodata.perusahaan_id dipindahkan.
4. EvaluasiAtasan.perusahaan_id dan jawaban tracer yang menyimpan nama pending diperbarui pada field yang ditentukan.
5. Pending company dihapus hanya setelah referensi berpindah.
6. Audit log mencatat sumber, target, aktor, dan perubahan.
7. Jika salah satu langkah gagal, transaksi rollback dan tidak boleh menampilkan sukses.

### AD-17A — Keputusan approval perusahaan

**Swimlane:** Admin Prodi/Admin Fakultas/Superadmin, Backend, Database.
**Prasyarat:** AD-16 sudah menghasilkan record pending dan rekomendasi master terverifikasi.
**Hasil:** perusahaan menjadi Terverifikasi, Ditolak, atau diteruskan ke AD-17 untuk konsolidasi master.

1. Admin membuka detail perusahaan pending dan membaca data pengajuan serta rekomendasi yang sudah dihitung sistem.
2. Backend memuat record berdasarkan scope controller. Admin tidak menghitung ulang atau memilih algoritma rekomendasi.
3. Admin memilih tindakan bisnis:
   - Verifikasi langsung jika record pending dianggap sebagai entitas master yang benar;
   - Edit lalu verifikasi jika data perusahaan perlu diperbaiki;
   - Ganti ke master jika rekomendasi menunjuk entitas master yang sama;
   - Tolak jika pengajuan tidak dapat digunakan.
4. Verifikasi langsung mengubah record pending menjadi Terverifikasi dan menulis LogActivity.
5. Edit lalu verifikasi memvalidasi field koreksi, menyimpan perubahan, mengubah status menjadi Terverifikasi, dan menulis LogActivity.
6. Ganti ke master memanggil AD-17 Auto-replace. Proses tersebut mengalihkan biodata, evaluasi atasan, dan jawaban tracer dalam satu transaksi.
7. Tolak mengubah status menjadi Ditolak, menyimpan alasan jika tersedia, dan menulis LogActivity.
8. Respons sukses hanya dikirim setelah operasi database selesai. Error scope, resource tidak ditemukan, validasi, atau rollback tidak boleh ditampilkan sebagai sukses.

~~~mermaid
flowchart TD
  A[Admin membuka detail pending] --> B[Backend memuat pending dan rekomendasi]
  B --> C{Admin memilih tindakan}
  C -- Verifikasi langsung --> V[Set pending menjadi Terverifikasi dan log]
  C -- Edit lalu verifikasi --> E[Validasi field, update, set Terverifikasi, log]
  C -- Ganti ke master --> R[Panggil AD-17 Auto-replace]
  C -- Tolak --> X[Set Ditolak, simpan alasan, log]
  V --> Z((Selesai))
  E --> Z
  R --> Z
  X --> Z
~~~

### AD-18 â€” Sinkronisasi LinkedIn satu alumni via Apify

**Swimlane:** Admin Prodi/Admin Fakultas/Superadmin, Backend, Apify API, Database.
**Route:** shared LinkedIn sync sync-single.
**Hasil:** snapshot pending tersimpan atau error provider; biodata utama belum berubah sebelum review.

1. Admin memilih alumni dalam scope dan meminta sinkronisasi.
2. Backend memeriksa scope, URL LinkedIn valid, konfigurasi token/provider, dan batas satu request.
3. Backend mengirim POST synchronous ke actor Apify data_forge_org~linkedin-scraper dengan bearer token.
4. Apify mengembalikan dataset item atau status HTTP error.
5. Backend memetakan response ke snapshot, menyimpan linkedin_sync_results berstatus pending, dan menulis log.
6. Status 400/401/402/404/429/5xx ditangani sebagai error provider; tidak dianggap berhasil.
7. Request Inertia diarahkan kembali dengan flash; request fetch menerima JSON yang disepakati.

~~~mermaid
flowchart LR
  A[Admin berwenang] --> B[Backend scope dan validasi URL]
  B --> D{Provider Apify aktif?}
  D -- Tidak --> E[Gunakan provider resmi/mock]
  D -- Ya --> P[Apify API: jalankan actor dan ambil dataset]
  P --> H{HTTP dan payload valid?}
  H -- Tidak --> X[Catat error dan kembalikan status gagal]
  H -- Ya --> M[Backend map snapshot]
  E --> M
  M --> DB[Database: simpan hasil pending dan log]
  DB --> R[Backend kirim redirect Inertia atau JSON]
  X --> R
~~~

### AD-19 â€” Sinkronisasi LinkedIn provider resmi/mock dan batch

**Swimlane:** Admin berwenang, Backend, LinkedIn provider, Database.
**Hasil:** satu atau beberapa hasil sync tersimpan; batch terisolasi per alumni.

1. Backend memilih provider dari konfigurasi.
2. Provider resmi/mock mengambil profil berdasarkan username/URL.
3. LinkedInProfileService dan mapper mengubah response ke DTO.
4. Satu alumni diproses dalam transaksi; hasil gagal tidak menggagalkan alumni lain pada batch.
5. Batch Apify dinonaktifkan karena perlindungan biaya; provider official dapat diproses satu per satu.
6. Status SUCCESS, NOT_FOUND, SKIPPED, atau ERROR disimpan dan dilaporkan.

### AD-20 â€” Review, approve, plot ulang, dan mapping LinkedIn

**Swimlane:** Admin berwenang, Backend, Database, Image/third-party URL.
**Hasil:** snapshot disetujui dan dipetakan ke biodata, atau tetap pending/ditolak.

1. Admin membuka hasil pending dan memeriksa snapshot.
2. Approve mengunci hasil pending dalam transaksi dan memilih posisi terkini/terbaru.
3. Mapper memperbarui posisi/perusahaan/URL/handle, mengaitkan foto dari URL jika tersedia, serta mengisi pendidikan/skills bila aturan isi jika kosong berlaku.
4. Riwayat pengalaman ditambahkan ke representasi yang ada tanpa duplikasi; data existing tidak boleh dihapus.
5. Plot ulang memanggil logika apply yang sama dengan flag plot_ulang; history lama dipertahankan.
6. Approve mengubah status hasil menjadi approved dan menulis log.
7. Reject hanya mengubah status hasil pending menjadi rejected; biodata utama tidak berubah.

### AD-21 â€” Status kelengkapan dan audit data

**Swimlane:** Backend, Database, Administrator.
**Hasil:** status profile, tracer universitas, tracer prodi, dan gabungan dapat ditampilkan.

1. Database view menyediakan v_alumni_profile_summary, status tracer, dan v_alumni_audit_rekap.
2. KelengkapanTracerService membaca pertanyaan aktif dan jawaban.
3. Pertanyaan wajib dihitung; pertanyaan opsional dapat ditampilkan sebagai informasi terpisah.
4. Pertanyaan yang tidak berlaku karena branching dikeluarkan dari kekurangan.
5. Backend menggabungkan status sesuai dashboard; kuesioner prodi tidak boleh diam-diam dihitung sebagai kuesioner universitas.
6. Jika tidak ada populasi, denominator ditangani sebagai kondisi khusus agar tidak menghasilkan angka menyesatkan.

### AD-22 â€” Audit log

**Swimlane:** Superadmin, Backend, Database.
**Route:** /superadmin/log-aktivitas.
**Hasil:** log terfilter ditampilkan.

1. Superadmin memilih search/filter user, action, atau waktu.
2. Backend memvalidasi filter dan eager-load relasi user.
3. Database mengembalikan log aktivitas profile, company, LinkedIn, akun, dan tindakan lain yang dicatat.
4. Backend mengirim halaman Inertia; log tidak diubah dari halaman baca.

### AD-23 â€” Evaluasi publik oleh atasan

**Swimlane:** Atasan, Backend, Database.
**Pemicu:** token undangan dibuka.
**Hasil:** evaluasi tersimpan atau ditolak.

1. Atasan membuka URL token.
2. Backend mencari token dan memeriksa status/validitas.
3. Jika valid, instrumen aktif dan identitas konteks ditampilkan tanpa membuka data yang tidak diperlukan.
4. Atasan mengirim nama, jabatan, email, telepon, perusahaan, kota, skor, dan catatan.
5. Backend menjalankan validasi dan throttle; data atasan/respons di-upsert.
6. Token invalid, throttle, atau data tidak valid menghasilkan error final dan tidak dianggap submitted.

### AD-24 â€” Navigasi respons Inertia dan error akses

**Swimlane:** Pengguna, Frontend Inertia, Backend, Database.
**Tujuan:** mencegah 404/403 dan plain JSON pada navigasi halaman.

1. Frontend mengirim GET halaman sebagai request Inertia.
2. Backend memeriksa autentikasi dan role; role gagal menghasilkan 403, resource/scope tidak ada menghasilkan 404.
3. Untuk mutation dari Inertia, backend melakukan redirect/flash atau validation error Inertia.
4. JSON hanya dipakai ketika request secara eksplisit fetch/API sesuai negosiasi response controller.
5. Frontend menampilkan error dan tidak menganggap response JSON sebagai halaman Inertia.
6. Browser log dan route list menjadi bukti verifikasi saat mendiagnosis masalah.

## 6. Traceability ringkas

| Diagram | Route/controller utama | Service/model/view utama |
|---|---|---|
| AD-01 | LoginController, LupaKataSandiController, UbahKataSandiController | User, Biodata, ResetPasswordMail, password_reset_tokens |
| AD-02 | ProfilController, SimpanProfilController, admin profile controllers | AdminAlumniProfileService, Biodata, Atasan, Perusahaan, KuesionerSyncService |
| AD-03 | shared /alumni/company | Perusahaan, Biodata, PerusahaanController |
| AD-04 | KuesionerController, SimpanJawabanController | Tracer, Kuesioner, KelompokPertanyaan, KelengkapanTracerService |
| AD-05 | KuesionerProdiController | ProdiQuestionSection, ProdiQuestion, ProdiQuestionOption, ProdiResponse |
| AD-06/23 | EvaluasiAtasanController, /alumni/kirim-email-atasan | Atasan, EvaluasiAtasan, PertanyaanEvaluasiAtasan, ResponEvaluasiAtasan, mail |
| AD-07/08 | directory controllers per role | AlumniDirectoryQueryService, AlumniTracerExcelExporter, mail |
| AD-09/10 | dashboard controllers | audit views, KelengkapanTracerService, role scope |
| AD-11/12/13 | question management controllers | questionnaire and option models |
| AD-14 | account management controllers | User, Biodata, LogActivity, mail |
| AD-15 | master wilayah/UMP controller | Propinsi, Kabupaten, Ump |
| AD-16/17 | company controllers | PerusahaanVerificationService, Perusahaan, Biodata, Tracer, evaluasi, log |
| AD-18/19/20 | Shared/LinkedInSyncController | LinkedIn providers, mapper, LinkedinSyncResult, AlumniLinkedinPlot, Biodata |
| AD-21 | dashboard/audit callers | KelengkapanTracerService, v_alumni_*, v_alumni_audit_rekap |
| AD-22 | log controller | LogActivity |
| AD-24 | all Inertia mutation controllers | RoleMiddleware, CheckMustChangePassword, response negotiation |

Traceability lengkap per method dan path file tersedia di docs/uml/activity-diagrams/TRACEABILITY-MATRIX.md.

## 7. Temuan analisis dan batas implementasi

1. Biro 3 bukan administrator tulis. Detail update secara eksplisit ditolak; dokumentasi tidak boleh menggambarkannya sebagai editor.
2. Sinkronisasi Apify bukan queue, polling, atau webhook pada source yang diaudit. Diagram memodelkannya sebagai request synchronous.
3. Tidak ditemukan app/Policies, Job, Event, Listener, atau command bisnis. Jika fitur baru menambah asynchronous flow, diagram harus direvisi.
4. Otorisasi tersebar antara middleware role dan query scope controller/service. Pengujian resource di luar scope wajib dilakukan untuk mencegah 403/404 yang tidak konsisten.
5. Response tracer memiliki format answer dan answer_json; relasi question_id tersedia dan sebaiknya menjadi acuan maintenance. Struktur lama/alias perlu diperlakukan hati-hati saat migrasi.
6. Riwayat pengalaman LinkedIn pada implementasi yang diaudit masih dipetakan ke representasi biodata; belum ada bukti tabel pengalaman relasional yang menjadi sumber utama. Diagram tidak mengklaim tabel baru.
7. Kelengkapan harus membedakan wajib, opsional, dan tidak berlaku karena branching. Kartu gabungan hanya boleh memakai komponen yang memang dihitung oleh backend.
8. Token evaluasi atasan adalah kredensial akses dan tidak seharusnya ditampilkan pada payload halaman admin yang tidak membutuhkan token.
9. Perubahan data profil, perusahaan, akun, dan LinkedIn harus tetap menulis audit log untuk traceability.
10. Tidak ada perubahan source code/database yang dilakukan ketika membuat dokumentasi ini.

## 8. Verifikasi kelengkapan dokumentasi

- [x] Route, controller, service, model, migration, view, mail, middleware, dan integrasi utama diaudit.
- [x] Lima stakeholder utama dipisahkan berdasarkan scope aktual.
- [x] Backend, database, mail service, dan Apify/third party dipisahkan sebagai swimlane ketika berpartisipasi.
- [x] Alur login, profile, tracer, evaluasi, direktori, dashboard, question management, akun, master data, perusahaan, LinkedIn, audit, dan log terwakili.
- [x] Cabang validasi, scope, status, threshold, provider, dan error dicatat.
- [x] Tidak ada diagram yang mengklaim Job/Event/Policy yang tidak ditemukan.
- [x] Source PlantUML dan dokumen pendukung tersedia pada docs/uml/activity-diagrams.
- [ ] SVG/PNG baru: tidak dirender karena PlantUML CLI/JAR tidak tersedia di environment audit ini. File SVG/PNG lama di docs/images bukan pengganti source PlantUML baru.

## 9. Artefak pendukung

- Inventaris fitur: docs/uml/activity-diagrams/INVENTARIS-FITUR.md
- Matriks hak akses: docs/uml/activity-diagrams/MATRIKS-HAK-AKSES.md
- Daftar activity diagram: docs/uml/activity-diagrams/DAFTAR-ACTIVITY-DIAGRAM.md
- Traceability matrix: docs/uml/activity-diagrams/TRACEABILITY-MATRIX.md
- Temuan analisis: docs/uml/activity-diagrams/TEMUAN-ANALISIS.md
- README UML: docs/uml/activity-diagrams/README.md
