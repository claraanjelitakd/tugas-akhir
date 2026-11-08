# Audit Konsistensi Data Wilayah

Tanggal audit: 8 November 2026
Database runtime aktif: `tracer` (schema kanonik hasil cutover). Database legacy
disimpan sebagai arsip `tracer_legacy_20261102`.

## Kesimpulan

Tidak ditemukan orphan relation, kode provinsi duplikat, atau pasangan UMP
per provinsi-tahun yang duplikat pada database runtime.

Data UMP 2026 sekarang bersumber dari
`database/data/ump_2026.csv`. Kolom `besaran` hanya menerima angka rupiah
tanpa simbol, titik, atau pemisah ribuan. `UmpSeeder` memvalidasi header,
kode sumber, tahun, nominal, dan duplikasi sebelum melakukan update dalam
transaksi.

Ada satu pengecualian desain yang perlu dipahami saat membaca ERD:

- `kabupaten.kode_provinsi`, `biodata.kode_provinsi`, dan `perusahaan.kode_provinsi`
  memakai `propinsi.kode_provinsi` sebagai foreign key.
- `ump.kode_provinsi` memakai `propinsi.kode_provinsi` sebagai foreign key
  natural key yang sama dengan seluruh tabel wilayah.
- Seluruh payload baru memakai nama kanonik `kode_provinsi` dan `kode_kabupaten`; alias
  `provinsi_id`/`province_id` dihapus.
- Data sekolah asal tidak lagi menjadi bagian dari schema aktif
  `data_akademik`
  sebagai catatan historis.
- `perusahaan.kode_negara` memakai `ref_negara.kode_negara` sebagai foreign key canonical
  natural key (`ON UPDATE CASCADE`, `ON DELETE RESTRICT`).

### Mapping kode sumber UMP

Kode pada CSV sumber resmi tidak seluruhnya sama dengan kode yang sudah dipakai
master wilayah aplikasi, khususnya provinsi Papua. Seeder mencocokkan nama
provinsi setelah menghapus alias dalam tanda kurung, lalu menyimpan
`propinsi.kode_provinsi` yang sudah menjadi foreign key canonical. Dengan cara
ini, data kabupaten/kota dan data perusahaan lama tidak ikut diremap.

| Nama pada CSV | Kode sumber | Kode master aplikasi |
| --- | ---: | ---: |
| Papua Barat | 91 | 92 |
| Papua Barat Daya | 92 | 96 |
| Papua | 94 | 91 |
| Papua Selatan | 95 | 93 |
| Papua Tengah | 96 | 94 |
| Papua Pegunungan | 97 | 95 |

## Pemeriksaan runtime

| Pemeriksaan | Hasil |
| --- | ---: |
| Baris `propinsi` | 38 |
| Baris `ump` | 38 |
| Kode provinsi tanpa pasangan master | 0 |
| Kode provinsi duplikat | 0 |
| Pasangan UMP provinsi-tahun duplikat | 0 |
| `perusahaan.kode_provinsi` orphan | 0 |
| `biodata.kode_provinsi` orphan | 0 |
| `kabupaten.kode_provinsi` orphan | 0 |
| `perusahaan.kode_negara` orphan | 0 |
| `users.prodi_id` orphan | 0 |
| `users.fakultas_id` orphan | 0 |
| `perusahaan.created_by_prodi_id` orphan | 0 |
| Kolom legacy sekolah/place/email pada `data_akademik` | 0 |

Seeder UMP memperbarui nominal tahun yang ada di CSV dan menghapus baris pada
tahun tersebut yang tidak lagi termasuk daftar provinsi sumber. Tahun UMP
lainnya tidak dihapus, sehingga histori tetap aman.

## Temuan kode

`database/factories/UmpFactory.php` sebelumnya mengimpor `App\Models\Province`,
sedangkan model aktif bernama `App\Models\Propinsi`. Factory sekarang memakai
model yang benar dan dilindungi oleh feature test.

## Keputusan kompatibilitas

Audit ini tetap mempertahankan `ump.kode_provinsi` sebagai natural key yang
memang ditetapkan ERD. Schema `tracer` sudah dibangun ulang dari migration
kanonik dan data kompatibel diimpor dari backup `tracer_legacy_20261102`;
backup SQL permanen berada di `storage/backups/` dan diabaikan Git.
