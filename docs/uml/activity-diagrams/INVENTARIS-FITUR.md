# Inventaris Fitur untuk Activity Diagram

Audit sumber: routes/web.php, app/Http/Controllers, app/Services, app/Models, app/Http/Middleware, database/migrations, resources/js/Pages, app/Mail, dan database views. Route non-vendor yang terdaftar saat audit: 185.

| No | Modul | Fitur | Aktor | Trigger | Data/komponen | AD |
|---:|---|---|---|---|---|---|
| 1 | Auth | Login, legacy fallback, logout | Semua pengguna | Form auth | User, Biodata, session, LogActivity | 01 |
| 2 | Auth | Lupa dan reset password | Tamu | Form email/token | password_reset_tokens, ResetPasswordMail | 01 |
| 3 | Auth | Wajib ganti password | Alumni | Middleware | User.must_change_password | 01 |
| 4 | Profil | Baca dan simpan profil | Alumni/admin scope | Submit form | Biodata, Atasan, Perusahaan | 02 |
| 5 | Perusahaan | Ajukan perusahaan alumni | Alumni | POST company | Perusahaan, Biodata | 03 |
| 6 | Tracer universitas | Jawaban parsial/final dan branching | Alumni | POST kuesioner | Kuesioner, KelompokPertanyaan, Tracer | 04 |
| 7 | Tracer prodi | Jawaban terstruktur prodi | Alumni | POST kuesioner prodi | ProdiQuestion*, ProdiResponse | 05 |
| 8 | Evaluasi | Undangan dan jawaban atasan | Alumni/Atasan | Tombol/email/token | Atasan, EvaluasiAtasan, ResponEvaluasiAtasan | 06, 23 |
| 9 | Direktori | Search, filter, detail, update, export, reminder | Admin scope/Biro 3 | Halaman dan action | DirectoryQuery, exporter, mail | 07, 08 |
| 10 | Dashboard | Dashboard prodi/fakultas | Admin Prodi/Fakultas | GET dashboard | audit views, scope query | 09 |
| 11 | Dashboard | Dashboard universitas | Superadmin | GET/filter tahun | audit views, aggregate query | 10 |
| 12 | Instrumen universitas | Section/question/option/reorder/toggle | Superadmin | CRUD | Kuesioner, KelompokPertanyaan, RefSubpertanyaan | 11 |
| 13 | Instrumen prodi | Section/question/option/reorder | Role scope | CRUD | ProdiQuestion* | 12 |
| 14 | Instrumen atasan | CRUD/toggle/reorder | Superadmin | CRUD | PertanyaanEvaluasiAtasan | 13 |
| 15 | Akun | Edit akun dan reset | Admin scope/Superadmin | Action | User, Biodata, mail, log | 14 |
| 16 | Master wilayah | Provinsi/kabupaten/UMP/copy year | Superadmin | CRUD | Propinsi, Kabupaten, Ump | 15 |
| 17 | Perusahaan | Similarity, geographic score, verify/reject | Admin scope | Review action | PerusahaanVerificationService, LogActivity | 16 |
| 18 | Perusahaan | Auto-replace pending | Admin scope | Replace action | Biodata, Tracer, EvaluasiAtasan, Perusahaan | 17 |
| 19 | LinkedIn | Apify single sync | Admin Prodi/Fakultas/Superadmin | Sync action | Apify provider, result staging | 18 |
| 20 | LinkedIn | Official/mock and batch sync | Admin scope | Batch/single action | LinkedInProfileService, mapper | 19 |
| 21 | LinkedIn | Approve, plot again, reject, history | Admin scope | Review action | LinkedinSyncResult, AlumniLinkedinPlot, Biodata | 20 |
| 22 | Audit | Profile/tracer/combined completion | Backend/dashboard | Query | KelengkapanTracerService, audit views | 21 |
| 23 | Log | Filter audit log | Superadmin | GET/filter | LogActivity | 22 |
| 24 | HTTP boundary | Inertia redirect/JSON/403/404 | Semua role | Request | middleware and controllers | 24 |

## Komponen yang tidak ditemukan

- Tidak ada app/Policies.
- Tidak ada Job, Event, Listener, atau command bisnis yang ditemukan.
- Tidak ada alur import bisnis yang terpisah dari controller yang diaudit.
- Apify tidak dipetakan sebagai queue, polling, atau webhook; provider dipanggil secara synchronous.
