# Audit Konsistensi Data Wilayah

Tanggal audit: 2 November 2026
Database runtime: `tracer_study`

## Kesimpulan

Tidak ditemukan orphan relation, kode provinsi duplikat, atau pasangan UMP
per provinsi-tahun yang duplikat pada database runtime.

Ada satu pengecualian desain yang perlu dipahami saat membaca ERD:

- `kabupaten.propinsi_id`, `biodata.propinsi_id`, dan `perusahaan.propinsi_id`
  memakai `propinsi.id` sebagai foreign key.
- `ump.kode_provinsi` memakai `propinsi.kode_provinsi` sebagai foreign key
  natural key. Ini sesuai sumber data UMP yang diterbitkan berdasarkan kode
  BPS/Kemendagri dan bukan data yang tidak memiliki relasi.
- `provinsi_id` pada payload tertentu adalah alias frontend/controller untuk
  `propinsi_id`, bukan kolom database baru.
- `data_akademik.provinsi_asal_sekolah` adalah teks asal sekolah, sehingga
  tidak boleh diperlakukan sebagai relasi master wilayah.
- `ref_negara` dipakai sebagai master validasi untuk `perusahaan.negara`,
  tetapi belum menjadi foreign key. ERD menggambarkannya sebagai relasi logis
  bergaris putus-putus agar hubungan bisnis terlihat tanpa memalsukan schema.

## Pemeriksaan runtime

| Pemeriksaan | Hasil |
| --- | ---: |
| Baris `propinsi` | 38 |
| Baris `ump` | 38 |
| Kode provinsi tanpa pasangan master | 0 |
| Kode provinsi duplikat | 0 |
| Pasangan UMP provinsi-tahun duplikat | 0 |
| `perusahaan.propinsi_id` orphan | 0 |
| `biodata.propinsi_id` orphan | 0 |
| `kabupaten.propinsi_id` orphan | 0 |

## Temuan kode

`database/factories/UmpFactory.php` sebelumnya mengimpor `App\Models\Province`,
sedangkan model aktif bernama `App\Models\Propinsi`. Factory sekarang memakai
model yang benar dan dilindungi oleh feature test.

## Keputusan kompatibilitas

Audit ini tidak mengganti `ump.kode_provinsi` menjadi `propinsi_id` pada branch
ini. Perubahan tersebut akan memerlukan backfill data UMP runtime, penyesuaian
seeder, controller, model, view analitik, serta migrasi fresh-install dan
database lama secara bersamaan. Sampai refactor tersebut disepakati, ERD
menjelaskan pengecualian natural key secara eksplisit agar tidak dianggap
inkonsistensi data.
