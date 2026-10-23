# Traceability Matrix Activity Diagram ke Implementasi

| AD | Route/pemicu | Controller/path utama | Service/model/view | Validasi/guard penting |
|---|---|---|---|---|
| 01 | /login, /forgot-password, /reset-password, /change-password, /logout | app/Http/Controllers/Otentikasi/LoginController.php; LupaKataSandiController.php; UbahKataSandiController.php | User, Biodata, ResetPasswordMail, password_reset_tokens | Auth, throttle, password 12+, must_change_password |
| 02 | /alumni/profile; admin profile update | app/Http/Controllers/Alumni/Profil/ProfilController.php; SimpanProfilController.php | AdminAlumniProfileService, Biodata, Atasan, Perusahaan, KuesionerSyncService | Form validation, role scope, normalization, partial supervisor preservation |
| 03 | /alumni/company | shared route in routes/web.php; company controller | Perusahaan, Biodata | Role group and input validation |
| 04 | /alumni/kuesioner | app/Http/Controllers/Alumni/Kuesioner/KuesionerController.php; SimpanJawabanController.php | Kuesioner, KelompokPertanyaan, Tracer, KelengkapanTracerService | question type, options, branching, question_id |
| 05 | /alumni/kuesioner-prodi | app/Http/Controllers/Alumni/Kuesioner/KuesionerProdiController.php | ProdiQuestionSection, ProdiQuestion, ProdiQuestionOption, ProdiResponse | prodi scope, answer normalization |
| 06 | /alumni/kirim-email-atasan | profile controller/shared route | Atasan, UndanganEvaluasiAtasanMail | email/phone/scope validation |
| 07 | admin directory routes | AdminProdi, AdminFakultas, SuperAdmin, directory controllers | AlumniDirectoryQueryService, AlumniTracerExcelExporter | role scope, filters, export |
| 08 | /biro3/dashboard, /biro3/alumni/* | app/Http/Controllers/Biro3/* | directory service, exporter, PengingatKuesionerAlumniMail | read-only update guard, incomplete target |
| 09 | /prodi/dashboard, /fakultas/dashboard | dashboard controllers by role | audit views, KelengkapanTracerService | prodi_id/fakultas_id scope |
| 10 | /superadmin/dashboard | app/Http/Controllers/SuperAdmin/DashboardSuperAdminController.php | v_alumni_audit_rekap, audit services | year filter, university scope |
| 11 | /superadmin/pertanyaan, /sections, /kuesioner | SuperAdmin question controllers | Kuesioner, KelompokPertanyaan, RefSubpertanyaan2021, RefSubpertanyaanDetil | active/status/order/options |
| 12 | /prodi/kuesioner/*, /fakultas/prodi-kuesioner/*, /superadmin/prodi-kuesioner/* | role-specific question controllers | ProdiQuestionSection, ProdiQuestion, ProdiQuestionOption | prodi/faculty scope |
| 13 | evaluation question routes | app/Http/Controllers/SuperAdmin/KelolaEvaluasiAtasanController.php | PertanyaanEvaluasiAtasan | Superadmin role, active/order |
| 14 | role management account routes | role-specific ManajemenAkun controllers | User, Biodata, LogActivity, reset mail | role/scope, unique fields |
| 15 | /superadmin/wilayah-ump/* | master wilayah controller | Propinsi, Kabupaten, Ump | parent relation, year, cascade behavior |
| 16 | company index/search/verify/update/reject/replace | app/Services/Perusahaan/PerusahaanVerificationService.php and company controllers | Perusahaan, LogActivity | similarity threshold, geographic score, role scope |
| 17 | replace endpoint | company controllers/service | Perusahaan, Biodata, Tracer, EvaluasiAtasan, LogActivity | transaction, source/target/status |
| 18 | linkedin sync single | app/Http/Controllers/Shared/LinkedInSyncController.php | ApifyLinkedInProvider, LinkedinSyncResult, LinkedInProfileMapper | URL, token, provider HTTP status, scope |
| 19 | linkedin sync batch/single official | same shared controller | LinkedInProfileService, ApiLinkedInProvider, MockLinkedInProvider | per-alumni isolation, status |
| 20 | approve/plot-again/reject/history | same shared controller | LinkedinSyncResult, AlumniLinkedinPlot, Biodata, mapper | pending lock, scope, no destructive overwrite |
| 21 | dashboard/audit calls | dashboard and directory controllers | KelengkapanTracerService, v_alumni_profile_summary, v_alumni_tracer_univ_status, v_alumni_tracer_prodi_status, v_alumni_audit_rekap | mandatory/optional/branching |
| 22 | /superadmin/log-aktivitas | LogAktivitasSuperAdminController | LogActivity, User | superadmin role, filter |
| 23 | /evaluasi-atasan/{token} | app/Http/Controllers/EvaluasiAtasanController.php | EvaluasiAtasan, PertanyaanEvaluasiAtasan, ResponEvaluasiAtasan | token, throttle, input validation |
| 24 | all Inertia mutation routes | routes/web.php plus controllers | RoleMiddleware, CheckMustChangePassword | Inertia redirect vs JSON negotiation, 403/404 |

## Route verification commands

Evidence can be refreshed with:

    php artisan route:list --except-vendor
    rg -n "Route::|function " routes app/Http/Controllers app/Services
