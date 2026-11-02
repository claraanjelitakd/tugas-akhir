# Temuan Analisis

## Temuan terverifikasi

1. Biro 3 memiliki kemampuan monitoring, ekspor, reminder, dan blast email, tetapi bukan role editor. Method perubahan profil menolak aksi.
2. Admin Prodi terbatas pada prodi milik user. Admin Fakultas dibatasi daftar prodi pada fakultas user. Superadmin berskala universitas.
3. Superadmin adalah role yang memiliki master wilayah/UMP, instrumen universitas, instrumen evaluasi atasan, audit log, dan seluruh LinkedIn/company management.
4. LinkedIn sync memakai staging result. Approve memetakan snapshot ke biodata. Reject tidak mengubah biodata utama.
5. Provider Apify dipanggil synchronous. Tidak ditemukan job, queue, polling, webhook, event, atau listener untuk proses ini.
6. Response HTTP dibedakan antara Inertia dan fetch/JSON. Plain JSON pada request Inertia adalah kondisi error, bukan hasil normal.
7. Database memiliki status audit/view untuk profil dan tracer. Pertanyaan tidak berlaku karena branching harus dikeluarkan dari kekurangan.
8. Tidak ditemukan app/Policies; guard utama berada di middleware role dan scope controller/service.

## Area yang harus diuji saat implementasi lanjutan

1. Semua route mutation Inertia harus mengembalikan redirect atau Inertia response; JSON hanya untuk request fetch yang memang meminta JSON.
2. ID resource dari role lain harus diuji agar menghasilkan 403/404 yang konsisten.
3. Update profile parsial tidak boleh menghapus relasi atasan atau perusahaan yang sudah ada.
4. Approval LinkedIn dan auto-replace harus diuji dengan transaksi gagal di tengah proses.
5. Batch LinkedIn harus tetap memproses alumni berikutnya ketika satu alumni gagal.
6. Kartu gabungan dashboard harus memiliki denominator dan komponen yang eksplisit; kuesioner prodi tidak boleh masuk ke kartu gabungan universitas tanpa keputusan bisnis.
7. Nilai mandatory yang dilompati karena branching tidak boleh dianggap kosong.
8. Token evaluasi atasan harus diperlakukan sebagai credential dan tidak dikirim ke payload yang tidak membutuhkannya.
9. Opsi Lainnya pada pertanyaan choice perlu menyimpan option/question identifier serta teks custom agar tetap dapat dirawat.
10. Pengalaman kerja LinkedIn masih berupa representasi pada biodata dalam implementasi yang diaudit; belum ada tabel relasional pengalaman sebagai sumber utama.

## Tidak dapat disimpulkan dari source

- Tidak ada bukti alur impor file massal yang berdiri sendiri.
- Tidak ada bukti pekerjaan scheduler otomatis.
- Tidak ada bukti webhook Apify.
- Tidak ada bukti bahwa Biro 3 boleh melakukan verifikasi perusahaan.
- Tidak ada bukti bahwa semua gambar lama di docs/images berasal dari diagram source terbaru.

## Dampak dokumentasi

Diagram hanya menggambarkan perilaku yang ditemukan. Fitur baru seperti pengalaman kerja relasional, queue LinkedIn, atau hak edit Biro 3 harus menambah/merevisi diagram serta traceability, bukan diasumsikan sudah ada.
