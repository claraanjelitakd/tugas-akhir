# Arsitektur & Panduan Integrasi LinkedIn (Official API & Apify Provider)

Dokumen ini menjelaskan arsitektur resmi, konfigurasi provider, alur kerja staging, dan penanganan data untuk fitur **Sinkronisasi Profil LinkedIn** pada aplikasi Tracer Study Alumni UKDW (SERU).

> Revisi 22 Oktober 2026: penyatuan sidebar dan pola halaman admin hanya memengaruhi konsistensi navigasi antarmuka. Kontrak provider, konfigurasi environment, staging `linkedin_sync_results`, serta aturan review LinkedIn tidak berubah.

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
- **Actor ID**: `data_forge_org~linkedin-scraper`
- **Endpoint**: `POST https://api.apify.com/v2/actors/{actorId}/run-sync-get-dataset-items`
- **Autentikasi**: `Authorization: Bearer {APIFY_API_TOKEN}` pada HTTP header (tidak pernah menggunakan query parameter `?token=...`).
- **Input Profil**: HANYA berasal dari kolom `biodata.linkedin_url`. Sistem menolak lookup berdasarkan nama, email, username, atau nomor telepon. Jika URL kosong atau format tidak valid, request dibatalkan sebelum memanggil Apify.

### Konfigurasi `.env`
```env
# Aktifkan Provider Apify
LINKEDIN_PROVIDER=apify

# Konfigurasi Token & Actor Apify (Hanya tersimpan di backend)
APIFY_API_TOKEN=apify_api_token_anda_di_sini
APIFY_LINKEDIN_ACTOR_ID=data_forge_org~linkedin-scraper
```

### Format Request Payload
Request sinkron dikirim dengan parameter minimal yang berfokus hanya pada data profil (menonaktifkan pos, komentar, dan lowongan untuk efisiensi waktu dan kuota):
```json
{
    "profileUrls": [
        "https://www.linkedin.com/in/contoh-alumni"
    ],
    "includeProfilePosts": false,
    "includeComments": false,
    "includeCompanyPosts": false,
    "includeCompanyJobs": false,
    "includeJobDetails": false
}
```

---

## 4. Alur Kerja Staging & Review Admin Berwenang (Prinsip Zero Direct Mutation)

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
[Data utama biodata alumni TIDAK BERUBAH SAMA SEKALI]
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

### Kebijakan Sync Ulang (Preservasi Histori)
Jika alumni yang sama disinkronkan kembali di lain waktu:
- Record staging yang lama **TIDAK DITIMPA / TIDAK DIHAPUS**.
- Sistem selalu membuat record baru dengan `status = pending`.
- Riwayat lengkap setiap eksekusi scraping dapat dipantau melalui fitur **Riwayat Sinkronisasi**.

---

## 5. Pemetaan Data (*Data Mapping*) Saat Disetujui (Approve)

Pemetaan hanya dijalankan setelah admin berwenang menekan tombol **Setujui (Approve)** pada hasil staging yang berstatus `pending`:
1. **Posisi / Jabatan**:
   - Jika tersedia entri pada array `experience`: entri posisi aktif terbaru diambil sebagai jabatan.
   - Jika tidak tersedia array pengalaman, gunakan `headline`.
   - Data disimpan ke kolom `posisi_jabatan` pada tabel `biodata`.
2. **Perusahaan / Institusi**:
   - Nama perusahaan dari entri pengalaman dicari pada master `perusahaan`.
   - Jika belum ada, sistem mendaftarkan institusi baru dengan `status_verifikasi = 'Menunggu Verifikasi'` (tidak langsung otomatis terverifikasi).
   - Foreign key `perusahaan_id` pada `biodata` diperbarui.
3. **Data Otoritatif Tetap Terlindungi**:
   - Kolom `NIM`, `NIK`, `NPWP`, `email_pribadi`, `nomor_telepon`, `tanggal_lulus`, dan IPK **TIDAK PERNAH DIUBAH** oleh sinkronisasi LinkedIn.
4. **Data Tanpa Mapping Jelas**:
   - Payload lengkap tetap tersimpan pada `scraped_data`; pemetaan approve juga dapat menerapkan `skills` dan `experience` ke kolom biodata. Field lain tanpa pemetaan tetap menjadi referensi payload.

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
   APIFY_API_TOKEN=token_asli_apify_anda
   APIFY_LINKEDIN_ACTOR_ID=data_forge_org~linkedin-scraper
   ```
2. Bersihkan cache konfigurasi:
   ```bash
   php artisan optimize:clear
   ```
3. Buka halaman SuperAdmin: **Sinkronisasi LinkedIn** (`/superadmin/linkedin-sync`).
4. Pilih 1 alumni yang memiliki `linkedin_url` publik valid.
5. Klik ikon petir **Sinkronkan**.
6. Amati status berubah menjadi badge kuning **Pending Review**.
7. Klik tombol review untuk memeriksa perbandingan data mentah hasil scraping dengan data utama saat ini.
8. Klik **Setujui Data (Approve)** atau **Tolak (Reject)** untuk menguji pembaruan data utama.

## 9. Lingkup akses terkini (21 Oktober 2026)

Controller bersama: `app/Http/Controllers/Shared/LinkedInSyncController.php`. Modul tersedia bagi Superadmin (`/superadmin/linkedin-sync`), Admin Prodi (`/prodi/linkedin-sync`) dan Admin Fakultas (`/fakultas/linkedin-sync`). Pembatasan alumni mengikuti prodi/fakultas. Biro III membaca riwayat hasil melalui detail alumni dan tidak memiliki endpoint sinkronisasi/review sendiri.

Nama berkas ini dipertahankan untuk kompatibilitas tautan lama. Implementasi memakai kontrak provider PHP dan HTTP API; nama berkas tidak membuktikan adanya server MCP LinkedIn. Driver bernama `official`/`api` adalah pilihan adapter aplikasi, bukan bukti akses produksi atau kemitraan resmi telah disediakan. Batch bukan antrean asinkron hanya karena disebut massal; provider Apify menolaknya dengan HTTP 400.

Lihat [DFD](dfd.md), proses 2 dan entitas E8, untuk hubungan data dengan sistem.
