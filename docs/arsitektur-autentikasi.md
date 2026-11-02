# Arsitektur Autentikasi

Dokumen ini menjelaskan batas tanggung jawab halaman autentikasi, komponen visual bersama, aset, dan endpoint yang digunakan oleh Tracer Study UKDW.

## Struktur komponen

```text
resources/js/
├── components/
│   └── Otentikasi/
│       └── AuthBackdrop.vue
└── Pages/
    └── Otentikasi/
        ├── Login.vue
        ├── ForgotPassword.vue
        ├── ResetPassword.vue
        └── UbahKataSandi.vue
```

`AuthBackdrop.vue` hanya bertanggung jawab atas presentasi bersama:

- foto background wisuda UKDW;
- overlay hijau untuk menjaga keterbacaan;
- slot untuk isi halaman autentikasi.

Komponen ini tidak melakukan autentikasi dan tidak memanggil backend.

## Alur halaman

```text
Login.vue
  ├── POST /login
  │     ├── berhasil -> redirect sesuai role
  │     ├── must_change_password = true -> /change-password
  │     └── gagal -> tampilkan error Inertia

  └── GET /forgot-password -> ForgotPassword.vue

ForgotPassword.vue
  └── POST /forgot-password
        ├── akun ditemukan -> email reset password
        └── gagal -> tampilkan error pada halaman

Link dari email
  ↓
ResetPassword.vue
  └── POST /reset-password
        ├── token valid -> password diperbarui -> /login
        └── token tidak valid/kedaluwarsa -> tampilkan error

Login dengan password awal
  ↓
UbahKataSandi.vue
  └── POST /change-password
        ├── valid -> password diperbarui dan flag must_change_password dimatikan
        └── gagal -> tampilkan error validasi
```

## Batas frontend dan backend

| Bagian | Tanggung jawab |
| --- | --- |
| Vue/Inertia | Menampilkan form, state loading, show/hide password, dan error validasi. |
| Route Laravel | Menentukan endpoint dan middleware request. |
| Controller Laravel | Memvalidasi input, memeriksa token, menyimpan password, dan menentukan redirect. |
| Service email | Mengirim tautan reset password; halaman email hanya mengarahkan ke `ResetPassword.vue`. |
| Database | Menyimpan user, password hash, status `must_change_password`, dan token reset sesuai mekanisme Laravel. |

Komponen Vue tidak melakukan hashing password, tidak memvalidasi token secara lokal, dan tidak menyimpan password ke local storage.

## Dokumentasi kode

Setiap halaman autentikasi memiliki dokumentasi di bagian paling atas yang menjelaskan tujuan halaman, endpoint, dan batas backend. Variabel `const` serta fungsi request memiliki komentar tepat sebelum deklarasinya. Komentar template dipakai pada wrapper dan section utama agar struktur visual dapat ditelusuri tanpa memenuhi markup dengan komentar untuk setiap elemen kecil.

Tidak ada service frontend baru pada alur ini. Request tetap memakai `useForm` Inertia agar response redirect dan error validasi diproses konsisten oleh aplikasi.

## Organisasi aset

```text
public/uploads/
├── auth/
│   ├── logo-ukdw.png
│   └── ukdw-wisuda.png
├── logo/       # logo bersama fitur lain
├── pigo/       # aset ilustrasi Pigo
└── profile/    # foto profil
```

Aset khusus autentikasi menggunakan path `/uploads/auth/...`. Aset bersama fitur lain tidak dipindahkan agar referensi halaman yang sudah ada tetap aman.
