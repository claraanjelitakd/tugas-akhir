# Arsitektur & Panduan Integrasi LinkedIn (Official API & Apify Provider)

Dokumen ini menjelaskan arsitektur resmi, konfigurasi provider, alur kerja staging, dan penanganan data untuk fitur **Sinkronisasi Profil LinkedIn** pada aplikasi Tracer Study Alumni UKDW (SERU).

> Revisi 06 November 2026: normalisasi field provider diperluas, mapping perusahaan memakai pencocokan master/similarity, dan tabel Sinkronisasi LinkedIn menyediakan approval cepat tanpa membuka detail.

---

## 1. Arsitektur Multi-Provider

Aplikasi mendukung dua kategori provider sinkronisasi LinkedIn melalui satu kontrak antarmuka yang terpadu: [`LinkedInProfileProvider`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/Contracts/LinkedInProfileProvider.php).

```text
                  LinkedInProfileProvider (Interface)
                               │
            ┌──────────────────┴──────────────────┐
            ▼                                     ▼
Provider 1: Official LinkedIn API       Provider 2: Apify Provider
(Server-to-Server / Mock)              (Third-Party Public Profile Scraper)
  - Driver: mock | api                   - Driver: apify
  - Input: username / url                - Input: biodata.linkedin_url murni
  - Direct update data alumni            - Staging: linkedin_sync_results (Pending)
                                         - SuperAdmin Review (Approve / Reject)
                                         - Cost Safety: 1 alumni per request
```

Pemilihan provider ditentukan secara dinamis melalui file `.env`:
- `LINKEDIN_PROVIDER=official` (Default, tidak mengubah perilaku existing)
- `LINKEDIN_PROVIDER=apify` (Third-party scraper publik)

---

## 2. Provider 1 — Official LinkedIn API (Server-to-Server / Mock)

### Karakteristik & Filosofi
- **Status**: Implementasi resmi server-to-server.
- **Driver Bawaan**: Dikelola oleh `LINKEDIN_DRIVER` di `.env` (`mock` untuk pengujian lokal, `api` untuk API resmi LinkedIn v2).
- **Alur Kerja**: Langsung memperbarui data jabatan/karier dan entitas perusahaan (dengan status *Menunggu Verifikasi*) melalui [`LinkedInProfileService.php`](https://github.com/claraanjelitakd/tracer-ta/blob/bea8270d6b2f92a598ed52ec862ea2f1260b2b75/app/Services/LinkedIn/LinkedInProfileService.php).
- **Mendukung**: Single Sync dan Bulk Batch Sync per angkatan.

### Konfigurasi `.env`
```env
# Pemilihan Provider Utama
LINKEDIN_PROVIDER=official

# Sub-driver Official (mock atau api)
LINKEDIN_DRIVER=mock
LINKEDIN_MOCK_PATH=storage/app/mock/linkedin
LINKEDIN_API_BASE_URL=https://api.linkedin.com/v2
LINKEDIN_API_KEY=
```

---

## 3. Provider 2 — Apify (Third-Party Public Profile Scraper)

> [!WARNING]
> **Pemberitahuan Lisensi & Keterbatasan**:
> Apify adalah penyedia layanan pihak ketiga (*third-party scraper*) untuk mengekstrak data profil LinkedIn publik dan **BUKAN** Official LinkedIn API. Scraping profil LinkedIn publik bergantung pada Actor pihak ketiga dan ketersediaan data publik yang tidak diprivatisasi oleh pengguna.

### Karakteristik & Spesifikasi
- **Actor Aktif**: `harvestapi/linkedin-profile-scraper` (Console ID: `LpVuK3Zozwuipa5bp`)
- **Endpoint**: `POST https://api.apify.com/v2/actors/harvestapi~linkedin-profile-scraper/run-sync-get-dataset-items`
- **Autentikasi**: `Authorization: Bearer {APIFY_API_TOKEN}` pada HTTP header (tidak pernah menggunakan query parameter `?token=...`).
- **Input Profil**: HANYA berasal dari kolom `biodata.linkedin_url`. Sistem menolak lookup berdasarkan nama, email, username, atau nomor telepon. Jika URL kosong atau format tidak valid, request dibatalkan sebelum memanggil Apify.
- **Normalisasi Atribut**: Mendukung struktur dataset HarvestAPI:
  - Nama: `firstName` + `lastName`
  - Posisi & Perusahaan Aktif: `currentPosition[0]`
  - Keahlian: Prioritas utama pada atribut `topSkills` (array maupun pemisah `•`), dengan fallback ke `skills`
  - Edukasi: `education` / `profileTopEducation`
  - Nilai Tambah: Menampung sertifikasi (`certifications`), kegiatan kepemimpinan/organisasi (`volunteering`), headline, dan lokasi terurai (`location.parsed`).

### Konfigurasi `.env`

#### Mode 1: Mock Apify (Pengujian Lokal & Hemat Saldo — Rekomendasi Development)
Tidak memakan kuota, tidak mengakses jaringan, membaca fixture JSON lokal di `storage/app/mock/apify`:
```env
LINKEDIN_PROVIDER=mock_apify
APIFY_MOCK_PATH=storage/app/mock/apify
# Token dapat dikomentari saat mode mock aktif
# APIFY_API_TOKEN=apify_api_...
APIFY_LINKEDIN_ACTOR_ID=harvestapi/linkedin-profile-scraper
```
*Catatan URL Mock*: Untuk membedakan pengujian mock dengan data asli, URL LinkedIn alumni mock diakhiri dengan `-mock` (contoh: `https://www.linkedin.com/in/jovanyulianto-mock`). Sistem secara cerdas memetakan handle tersebut ke fixture kanonik `storage/app/mock/apify/jovanyulianto.json`.

#### Mode 2: Live Apify Scraper (Penarikan Profil LinkedIn Nyata via Server Apify)
Memanggil server Apify secara langsung, memotong saldo/kredit resmi ($0.004 per penarikan):
```env
LINKEDIN_PROVIDER=apify
APIFY_API_TOKEN=apify_api_token_anda_di_sini
APIFY_LINKEDIN_ACTOR_ID=harvestapi/linkedin-profile-scraper
```
*Catatan URL Live*: Pastikan kolom URL LinkedIn pada data alumni menggunakan URL asli LinkedIn tanpa imbuhan `-mock` (contoh: `https://www.linkedin.com/in/jovanyulianto`).

### Format Request Payload
Request sinkron dikirim dengan parameter resmi HarvestAPI yang berfokus pada data profil utuh tanpa mengekstrak email berbayar untuk efisiensi biaya:
```json
{
    "profileScraperMode": "Profile details no email ($4 per 1k)",
    "queries": [
        "https://www.linkedin.com/in/contoh-alumni"
    ]
}
```

### Catatan Tampilan JSON Mentah vs Berkas Fixture
Saat melihat data pada tombol **{ } Lihat JSON** di antarmuka web, kunci-kunci objek JSON mungkin tampak berbeda urutannya dibandingkan dengan berkas `.json` mentah. Hal ini terjadi karena kolom `scraped_data` bertipe `JSON` pada MySQL. Berdasarkan spesifikasi biner MySQL JSON, **MySQL secara otomatis mengurutkan semua nama *key* secara alfabetis (A - Z)** saat disimpan ke basis data. Seluruh nilai data di dalamnya dijamin 100% identik.

---

## 4. Alur Kerja Staging, Mapping, dan Review Admin Berwenang

Snapshot LinkedIn tetap berstatus `pending`. Namun, saat snapshot dibuat,
perusahaan dapat langsung dicari ke master: kecocokan kuat dipetakan jika
relasi `biodata.perusahaan_id` masih kosong, sedangkan perusahaan baru dibuat
sebagai `Menunggu Verifikasi`. Field profil lain tetap tidak berubah sampai
approval. Jadi prinsipnya adalah **zero direct mutation untuk field profil**,
bukan menunda seluruh resolusi perusahaan.

Evaluasi atasan memakai master head office yang sama. Isian publik atasan hanya
melengkapi metadata yang masih kosong pada master terverifikasi; kode pos kantor
tidak dipakai sebagai kode pos domisili alumni.

Ketika tombol **Sinkronisasi LinkedIn** ditekan untuk satu alumni pada provider Apify:

```text
SuperAdmin Klik "Sinkronkan" (1 Alumni)
                 ↓
Validasi: biodata.linkedin_url valid
                 ↓
Kirim 1 Request POST ke Apify Actor via Bearer Token
                 ↓
Terima Raw JSON Dataset dari Apify
                 ↓
Simpan snapshot ke tabel: linkedin_sync_results (status: pending)
[Field profil utama tetap menunggu approval; mapping perusahaan berconfidence tinggi dapat diproses otomatis]
                 ↓
SuperAdmin membuka Review Hasil Sinkronisasi
                 ↓
       ┌─────────┴─────────┐
       ▼                   ▼
    Approve              Reject
       ↓                   ↓
- Terapkan mapping data  - Data utama TIDAK berubah
  ke biodata/perusahaan  - status: rejected
- status: approved       - reviewed_by & reviewed_at terisi
- reviewed_by terisi
- reviewed_at terisi
```

### Struktur Tabel Staging `linkedin_sync_results`
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | BigIncrements | Primary Key |
| `biodata_id` | Foreign Key | Terhubung ke `biodata.id` |
| `linkedin_url` | String | URL LinkedIn alumni yang discrape |
| `linkedin_username`| String (Nullable) | Username yang diekstrak dari URL |
| `scraped_data` | JSON | Response JSON mentah (*raw*) aktual dari Apify Actor |
| `status` | Enum | `pending`, `approved`, `rejected` |
| `reviewed_by` | Foreign Key (Nullable) | User ID admin yang melakukan review |
| `reviewed_at` | Timestamp (Nullable) | Waktu persetujuan / penolakan |
| `scraped_at` | Timestamp | Waktu data berhasil di-scrape |
| `created_at` / `updated_at` | Timestamp | Pencatatan riwayat |

### Mapping perusahaan dan approval cepat

Resolver perusahaan mengikuti urutan berikut:

1. Pencocokan nama eksak setelah spasi dan kapitalisasi dirapikan.
2. Rekomendasi similarity dari `PerusahaanVerificationService`.
3. Skor sangat tinggi diperlakukan sebagai kecocokan aman dan disiapkan untuk approval.
4. Nama yang belum pasti dibuat sebagai master `Menunggu Verifikasi`; relasi biodata tidak dipindahkan jika masih ada konflik perusahaan.

Data posisi, bidang, skills, experience, foto, dan studi lanjut tetap berada di
staging sampai approval profil. Tombol `Approve & Terapkan` dan `Tolak Hasil`
tersedia langsung pada baris pending; modal detail hanya diperlukan jika admin
ingin menyesuaikan nilai hasil scraping.

### Kebijakan Sync Ulang (Preservasi Histori)
Jika alumni yang sama disinkronkan kembali di lain waktu:
- Record staging yang lama **TIDAK DITIMPA / TIDAK DIHAPUS**.
- Sistem selalu membuat record baru dengan `status = pending`.
- Riwayat lengkap setiap eksekusi scraping dapat dipantau melalui fitur **Riwayat Sinkronisasi**.

---

## 5. Pemetaan Data (*Data Mapping*) Saat Disetujui (Approve / Plot Ulang)

Pemetaan field profil dijalankan setelah admin berwenang menekan tombol **Setujui (Approve)** atau **Plot Ulang (Replot)**. Saat staging dibuat, sistem hanya menyiapkan kecocokan master dan rekomendasi tanpa mengubah profil utama:
1. **Posisi / Jabatan & Kategori**:
   - Jika karyawan/pekerja: entri posisi aktif terbaru diambil sebagai `posisi_jabatan` pada `biodata`, kategori diset `Pekerja`.
   - Jika wirausaha: posisi disimpan ke `posisi_wiraswasta` pada `biodata`, kategori diset `Wiraswasta`.
2. **Perusahaan / Institusi**:
   - Nama perusahaan dari entri pengalaman dicari pada master `perusahaan` dan rekomendasi similarity.
   - Jika belum ada, sistem mendaftarkan institusi baru dengan `status_verifikasi = 'Menunggu Verifikasi'`.
   - Foreign key `perusahaan_id` memakai master terverifikasi yang cocok setelah approval; nama yang benar-benar berbeda tetap menjadi kandidat `Menunggu Verifikasi` dan konflik tetap menunggu review.
3. **Bidang Pekerjaan / Divisi**:
   - Diekstrak dari objek industri HarvestAPI (`industry.name`), contoh: `"IT Services and IT Consulting"`.
   - Disimpan ke kolom `biodata.bidang_pekerjaan` dan dapat disesuaikan langsung oleh admin melalui modal approval.
4. **Informasi Studi Lanjut (Jenjang, Kampus, Program Studi)**:
   - Sistem secara cerdas mendeteksi entri pendidikan lanjutan dari array `education` (memprioritaskan S3/Doktor, S2/Magister, Profesi, non-S1 almamater UKDW).
   - Data otomatis memetakan `pendidikan_tingkat` (misal `S3` / `S2`), `perguruan_tinggi` (misal `Universitas Gadjah Mada (UGM)`), dan `pendidikan_prodi` (misal `Computer Software Engineering`).
   - Admin dapat memilih entri studi lanjut alternatif melalui *quick-pills* di modal peninjauan.
5. **Keahlian (Skills) & Pengalaman Kerja (Experience)**:
   - Prioritas mutlak pada atribut `topSkills` yang divalidasi pengguna di LinkedIn.
   - Riwayat posisi terformat ke multiline string di kolom `biodata.experience`.
6. **Data Otoritatif Tetap Terlindungi**:
   - Kolom `NIM`, `NIK`, `NPWP`, `email_pribadi`, `nomor_telepon`, `tanggal_lulus`, dan IPK **TIDAK PERNAH DIUBAH** oleh sinkronisasi LinkedIn.

---

## 6. Prinsip Cost Safety & Keamanan Token

1. **Aturan 1 Klik = 1 Request**:
   - Sinkronisasi Apify **hanya** dapat dilakukan per individu alumni (*single sync*).
   - Fitur **Sinkronisasi Massal (Bulk Sync)** otomatis dinonaktifkan (`HTTP 400`) saat `LINKEDIN_PROVIDER=apify` untuk mencegah pemborosan kredit aktor Apify.
   - Tidak ada scraping otomatis saat halaman dimuat (*page load*), loop tanpa batas, atau infinite retry.
2. **Keamanan Token Tanpa Bocor**:
   - `APIFY_API_TOKEN` hanya dibaca di backend melalui `config('services.apify.api_token')`.
   - Token dikirimkan lewat header `Authorization: Bearer ...` (bukan query string).
   - Token **TIDAK PERNAH** dikirimkan ke frontend Vue, tidak pernah dicetak di log aplikasi, terminal, SweetAlert2, maupun response JSON controller.

---

## 7. Penanganan Kesalahan (Error Handling)

Provider Apify menangani berbagai skenario kegagalan secara anggun (*graceful*):
- **Token Kosong**: Menolak request dan memberi instruksi admin untuk mengisi konfigurasi server.
- **URL Tidak Valid / Kosong**: Menolak request sebelum HTTP call dilakukan.
- **HTTP 400**: Input URL atau payload ditolak oleh Actor.
- **HTTP 401**: Token Apify tidak valid atau kedaluwarsa.
- **HTTP 402**: Kredit / kuota komputasi Apify habis.
- **HTTP 404**: Actor ID atau profil LinkedIn tidak ditemukan.
- **HTTP 429**: Terkena rate limiting Apify.
- **HTTP 500 / 502 / 504**: Masalah internal server Apify / timeout gateway.
- **Network Error / Timeout**: Koneksi internet terputus atau timeout melebihi ambang batas.
- **Empty Dataset / Malformed JSON**: Data profil kosong atau format respons rusak.

Pada seluruh kondisi error di atas:
- **TIDAK ADA** record berstatus `approved` yang dibuat.
- Data utama `biodata` alumni dijamin **TIDAK BERUBAH**.
- SuperAdmin menerima pesan notifikasi yang aman tanpa membocorkan kredensial.

---

## 8. Panduan Pengujian

### A. Pengujian Otomatis (Automated Tests)
Pengujian otomatis menggunakan HTTP Mock (`Http::fake()`) sehingga dapat berjalan 100% tanpa token riil dan tidak memakan kuota:
```bash
# Menjalankan seluruh test Apify Provider (23 skenario)
php artisan test --filter=ApifyLinkedInProviderTest

# Menjalankan pengujian integrasi SuperAdmin LinkedIn
php artisan test --filter=SuperAdminLinkedInSyncTest

# Menjalankan seluruh suite pengujian LinkedIn
php artisan test --filter=LinkedIn
```

### B. Pengujian Integrasi Manual (Live Apify Test)
1. Atur `.env` lokal:
   ```env
   LINKEDIN_PROVIDER=apify
   APIFY_API_TOKEN=apify_api_token_asli_anda
   APIFY_LINKEDIN_ACTOR_ID=harvestapi/linkedin-profile-scraper
   ```
2. Bersihkan cache konfigurasi jika diperlukan:
   ```bash
   php artisan optimize:clear
   ```
3. Buka halaman SuperAdmin: **Sinkronisasi LinkedIn** (`/superadmin/linkedin-sync`) atau halaman Detail Alumni (`/superadmin/alumni/{id}?tab=linkedin`).
4. Pastikan alumni yang diuji memiliki `linkedin_url` publik asli (tanpa akhiran `-mock`).
5. Klik tombol petir **Sinkronkan** atau **Sinkronkan Ulang**.
6. Amati status berubah menjadi badge kuning **Pending Review**.
7. Buka panel review atau tab LinkedIn untuk memeriksa hasil:
   - Tab **Perbandingan & Usulan Data**: jabatan, perusahaan, keahlian (`topSkills`), foto.
   - Tab **Riwayat Karier & Pendidikan**: pengalaman kerja terurai dan institusi studi.
   - Tombol **Lihat JSON**: melihat payload JSON mentah dari Apify.
8. Klik **Setujui & Terapkan Data (Approve)** atau **Tolak (Reject)** untuk menguji pembaruan data utama.
9. Setelah selesai, kembalikan `.env` ke `LINKEDIN_PROVIDER=mock_apify` agar saldo Apify tetap aman.

## 9. Lingkup akses terkini (21 Oktober 2026)

Controller bersama: `app/Http/Controllers/Shared/LinkedInSyncController.php`. Modul tersedia bagi Superadmin (`/superadmin/linkedin-sync`), Admin Prodi (`/prodi/linkedin-sync`) dan Admin Fakultas (`/fakultas/linkedin-sync`). Pembatasan alumni mengikuti prodi/fakultas. Biro III membaca riwayat hasil melalui detail alumni dan tidak memiliki endpoint sinkronisasi/review sendiri.

Nama berkas ini dipertahankan untuk kompatibilitas tautan lama. Implementasi memakai kontrak provider PHP dan HTTP API; nama berkas tidak membuktikan adanya server MCP LinkedIn. Driver bernama `official`/`api` adalah pilihan adapter aplikasi, bukan bukti akses produksi atau kemitraan resmi telah disediakan. Batch bukan antrean asinkron hanya karena disebut massal; provider Apify menolaknya dengan HTTP 400.

Lihat [DFD](dfd.md), proses 2 dan entitas E8, untuk hubungan data dengan sistem.
