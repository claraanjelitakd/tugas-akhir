<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat database view 'v_alumni_linkedin_plot' untuk pembacaan data teragregasi aplikasi.
 *
 * Definisi MySQL dan SQLite dipertahankan dalam satu migration karena sintaks
 * fungsi JSON, konkatenasi, dan casting berbeda antar driver database.
 */
return new class extends Migration
{
    /**
     * Membuat view 'v_alumni_linkedin_plot' setelah tabel sumber tersedia.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_linkedin_plot');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_alumni_linkedin_plot AS SELECT 
                b.id AS biodata_id,
                b.user_id,
                b.nim,
                COALESCE(da.nama, u.name, 'Mahasiswa UKDW') AS nama_alumni,
                b.prodi_id,
                p.kode_prodi,
                p.nama_prodi,
                p.fakultas_id,
                f.kode_fakultas,
                f.nama_fakultas,
                da.tahun_lulus,
                da.tahun_akademik_lulus,
                b.linkedin_url AS linkedin_url_biodata,
                b.linkedin_username AS linkedin_username_biodata,
                b.posisi_jabatan AS posisi_biodata,
                b.perusahaan_id AS perusahaan_id_biodata,
                c.nama_perusahaan AS perusahaan_biodata,
                b.skills AS skills_biodata,
                b.experience AS experience_biodata,
                
                lsr.id AS sync_result_id,
                lsr.status AS sync_status,
                lsr.scraped_at,
                lsr.reviewed_at,
                lsr.reviewed_by,
                u_rev.name AS reviewer_name,
                lsr.linkedin_url AS linkedin_url_scraped,
                lsr.linkedin_username AS linkedin_username_scraped,
                
                COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        ) AS nama_linkedin,
                COALESCE(
            json_extract(lsr.scraped_data, '$.job_title'),
            json_extract(lsr.scraped_data, '$[0].job_title'),
            json_extract(lsr.scraped_data, '$.current_job'),
            json_extract(lsr.scraped_data, '$[0].current_job'),
            json_extract(lsr.scraped_data, '$.title'),
            json_extract(lsr.scraped_data, '$[0].title'),
            json_extract(lsr.scraped_data, '$.position'),
            json_extract(lsr.scraped_data, '$[0].position'),
            json_extract(lsr.scraped_data, '$.experiences[0].title'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].title'),
            json_extract(lsr.scraped_data, '$.headline'),
            json_extract(lsr.scraped_data, '$[0].headline')
        ) AS posisi_linkedin,
                COALESCE(
            json_extract(lsr.scraped_data, '$.company_name'),
            json_extract(lsr.scraped_data, '$[0].company_name'),
            json_extract(lsr.scraped_data, '$.current_company'),
            json_extract(lsr.scraped_data, '$[0].current_company'),
            json_extract(lsr.scraped_data, '$.companyName'),
            json_extract(lsr.scraped_data, '$[0].companyName'),
            json_extract(lsr.scraped_data, '$.company'),
            json_extract(lsr.scraped_data, '$[0].company'),
            json_extract(lsr.scraped_data, '$.experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$.experiences[0].company'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company')
        ) AS perusahaan_linkedin,
                COALESCE(
            json_extract(lsr.scraped_data, '$.location'),
            json_extract(lsr.scraped_data, '$[0].location'),
            json_extract(lsr.scraped_data, '$.locationName'),
            json_extract(lsr.scraped_data, '$[0].locationName')
        ) AS lokasi_linkedin,

                CASE 
                    WHEN lsr.id IS NULL OR COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        ) IS NULL THEN 0
                    WHEN LOWER(TRIM(COALESCE(da.nama, u.name, ''))) = LOWER(TRIM(COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        ))) THEN 1
                    WHEN (
                        LENGTH(COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        )) >= 3
                        AND (
                LOWER(COALESCE(da.nama, u.name, '')) LIKE '%' || LOWER(COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        )) || '%'
                OR LOWER(COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        )) LIKE '%' || LOWER(COALESCE(da.nama, u.name, '')) || '%'
            )
                    ) THEN 1
                    ELSE 0
                END AS is_nama_sesuai,

                CASE 
                    WHEN lsr.id IS NULL OR COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        ) IS NULL THEN 'Belum Ada Data LinkedIn'
                    WHEN LOWER(TRIM(COALESCE(da.nama, u.name, ''))) = LOWER(TRIM(COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        ))) THEN 'Nama Sesuai Eksak'
                    WHEN (
                        LENGTH(COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        )) >= 3
                        AND (
                LOWER(COALESCE(da.nama, u.name, '')) LIKE '%' || LOWER(COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        )) || '%'
                OR LOWER(COALESCE(
            json_extract(lsr.scraped_data, '$.full_name'),
            json_extract(lsr.scraped_data, '$[0].full_name'),
            json_extract(lsr.scraped_data, '$.name'),
            json_extract(lsr.scraped_data, '$[0].name'),
            json_extract(lsr.scraped_data, '$.fullName'),
            json_extract(lsr.scraped_data, '$[0].fullName')
        )) LIKE '%' || LOWER(COALESCE(da.nama, u.name, '')) || '%'
            )
                    ) THEN 'Nama Sesuai Parsial'
                    ELSE 'Nama Berbeda (Perlu Verifikasi)'
                END AS keterangan_nama,

                CASE 
                    WHEN lsr.status = 'approved' THEN 1
                    WHEN (
                        b.perusahaan_id IS NOT NULL 
                        AND COALESCE(
            json_extract(lsr.scraped_data, '$.company_name'),
            json_extract(lsr.scraped_data, '$[0].company_name'),
            json_extract(lsr.scraped_data, '$.current_company'),
            json_extract(lsr.scraped_data, '$[0].current_company'),
            json_extract(lsr.scraped_data, '$.companyName'),
            json_extract(lsr.scraped_data, '$[0].companyName'),
            json_extract(lsr.scraped_data, '$.company'),
            json_extract(lsr.scraped_data, '$[0].company'),
            json_extract(lsr.scraped_data, '$.experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$.experiences[0].company'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company')
        ) IS NOT NULL
                        AND LOWER(TRIM(COALESCE(c.nama_perusahaan, ''))) = LOWER(TRIM(COALESCE(
            json_extract(lsr.scraped_data, '$.company_name'),
            json_extract(lsr.scraped_data, '$[0].company_name'),
            json_extract(lsr.scraped_data, '$.current_company'),
            json_extract(lsr.scraped_data, '$[0].current_company'),
            json_extract(lsr.scraped_data, '$.companyName'),
            json_extract(lsr.scraped_data, '$[0].companyName'),
            json_extract(lsr.scraped_data, '$.company'),
            json_extract(lsr.scraped_data, '$[0].company'),
            json_extract(lsr.scraped_data, '$.experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$.experiences[0].company'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company')
        )))
                    ) THEN 1
                    ELSE 0
                END AS is_terplot_ke_biodata,

                CASE 
                    WHEN lsr.id IS NULL THEN 'Belum Pernah Sync'
                    WHEN lsr.status = 'approved' THEN 'Sudah Terplot (Approved)'
                    WHEN lsr.status = 'pending' AND (
                        (
                            COALESCE(
            json_extract(lsr.scraped_data, '$.company_name'),
            json_extract(lsr.scraped_data, '$[0].company_name'),
            json_extract(lsr.scraped_data, '$.current_company'),
            json_extract(lsr.scraped_data, '$[0].current_company'),
            json_extract(lsr.scraped_data, '$.companyName'),
            json_extract(lsr.scraped_data, '$[0].companyName'),
            json_extract(lsr.scraped_data, '$.company'),
            json_extract(lsr.scraped_data, '$[0].company'),
            json_extract(lsr.scraped_data, '$.experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$.experiences[0].company'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company')
        ) IS NOT NULL
                            AND LOWER(TRIM(COALESCE(c.nama_perusahaan, ''))) = LOWER(TRIM(COALESCE(
            json_extract(lsr.scraped_data, '$.company_name'),
            json_extract(lsr.scraped_data, '$[0].company_name'),
            json_extract(lsr.scraped_data, '$.current_company'),
            json_extract(lsr.scraped_data, '$[0].current_company'),
            json_extract(lsr.scraped_data, '$.companyName'),
            json_extract(lsr.scraped_data, '$[0].companyName'),
            json_extract(lsr.scraped_data, '$.company'),
            json_extract(lsr.scraped_data, '$[0].company'),
            json_extract(lsr.scraped_data, '$.experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company_name'),
            json_extract(lsr.scraped_data, '$.experiences[0].company'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].company')
        )))
                        )
                        OR (
                            COALESCE(
            json_extract(lsr.scraped_data, '$.job_title'),
            json_extract(lsr.scraped_data, '$[0].job_title'),
            json_extract(lsr.scraped_data, '$.current_job'),
            json_extract(lsr.scraped_data, '$[0].current_job'),
            json_extract(lsr.scraped_data, '$.title'),
            json_extract(lsr.scraped_data, '$[0].title'),
            json_extract(lsr.scraped_data, '$.position'),
            json_extract(lsr.scraped_data, '$[0].position'),
            json_extract(lsr.scraped_data, '$.experiences[0].title'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].title'),
            json_extract(lsr.scraped_data, '$.headline'),
            json_extract(lsr.scraped_data, '$[0].headline')
        ) IS NOT NULL
                            AND LOWER(TRIM(COALESCE(b.posisi_jabatan, ''))) = LOWER(TRIM(COALESCE(
            json_extract(lsr.scraped_data, '$.job_title'),
            json_extract(lsr.scraped_data, '$[0].job_title'),
            json_extract(lsr.scraped_data, '$.current_job'),
            json_extract(lsr.scraped_data, '$[0].current_job'),
            json_extract(lsr.scraped_data, '$.title'),
            json_extract(lsr.scraped_data, '$[0].title'),
            json_extract(lsr.scraped_data, '$.position'),
            json_extract(lsr.scraped_data, '$[0].position'),
            json_extract(lsr.scraped_data, '$.experiences[0].title'),
            json_extract(lsr.scraped_data, '$[0].experiences[0].title'),
            json_extract(lsr.scraped_data, '$.headline'),
            json_extract(lsr.scraped_data, '$[0].headline')
        )))
                        )
                    ) THEN 'Data Sama / Siap Diplot'
                    WHEN lsr.status = 'pending' THEN 'Menunggu Review (Pending)'
                    WHEN lsr.status = 'rejected' THEN 'Ditolak (Rejected)'
                    ELSE 'Belum Terplot'
                END AS status_plot_label

            FROM biodata b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN data_akademik da ON da.nim = b.nim
            LEFT JOIN prodi p ON b.prodi_id = p.id
            LEFT JOIN ref_fakultas f ON p.fakultas_id = f.id
            LEFT JOIN perusahaan c ON b.perusahaan_id = c.id
            LEFT JOIN (
                SELECT l1.*
                FROM linkedin_sync_results l1
                INNER JOIN (
                    SELECT biodata_id, MAX(id) AS max_id
                    FROM linkedin_sync_results
                    GROUP BY biodata_id
                ) l2 ON l1.id = l2.max_id
            ) lsr ON lsr.biodata_id = b.id
            LEFT JOIN users u_rev ON lsr.reviewed_by = u_rev.id;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_linkedin_plot` AS select `b`.`id` AS `biodata_id`,`b`.`user_id` AS `user_id`,`b`.`nim` AS `nim`,coalesce(`da`.`nama`,`u`.`name`,'Mahasiswa UKDW') AS `nama_alumni`,`b`.`prodi_id` AS `prodi_id`,`p`.`kode_prodi` AS `kode_prodi`,`p`.`nama_prodi` AS `nama_prodi`,`p`.`fakultas_id` AS `fakultas_id`,`f`.`kode_fakultas` AS `kode_fakultas`,`f`.`nama_fakultas` AS `nama_fakultas`,`da`.`tahun_lulus` AS `tahun_lulus`,`da`.`tahun_akademik_lulus` AS `tahun_akademik_lulus`,`b`.`linkedin_url` AS `linkedin_url_biodata`,`b`.`linkedin_username` AS `linkedin_username_biodata`,`b`.`posisi_jabatan` AS `posisi_biodata`,`b`.`perusahaan_id` AS `perusahaan_id_biodata`,`c`.`nama_perusahaan` AS `perusahaan_biodata`,`b`.`skills` AS `skills_biodata`,`b`.`experience` AS `experience_biodata`,`lsr`.`id` AS `sync_result_id`,`lsr`.`status` AS `sync_status`,`lsr`.`scraped_at` AS `scraped_at`,`lsr`.`reviewed_at` AS `reviewed_at`,`lsr`.`reviewed_by` AS `reviewed_by`,`u_rev`.`name` AS `reviewer_name`,`lsr`.`linkedin_url` AS `linkedin_url_scraped`,`lsr`.`linkedin_username` AS `linkedin_username_scraped`,json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName'))) AS `nama_linkedin`,json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.job_title'),json_extract(`lsr`.`scraped_data`,'$[0].job_title'),json_extract(`lsr`.`scraped_data`,'$.current_job'),json_extract(`lsr`.`scraped_data`,'$[0].current_job'),json_extract(`lsr`.`scraped_data`,'$.title'),json_extract(`lsr`.`scraped_data`,'$[0].title'),json_extract(`lsr`.`scraped_data`,'$.position'),json_extract(`lsr`.`scraped_data`,'$[0].position'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].title'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].title'),json_extract(`lsr`.`scraped_data`,'$.headline'),json_extract(`lsr`.`scraped_data`,'$[0].headline'))) AS `posisi_linkedin`,json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.company_name'),json_extract(`lsr`.`scraped_data`,'$[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.current_company'),json_extract(`lsr`.`scraped_data`,'$[0].current_company'),json_extract(`lsr`.`scraped_data`,'$.companyName'),json_extract(`lsr`.`scraped_data`,'$[0].companyName'),json_extract(`lsr`.`scraped_data`,'$.company'),json_extract(`lsr`.`scraped_data`,'$[0].company'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company'))) AS `perusahaan_linkedin`,json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.location'),json_extract(`lsr`.`scraped_data`,'$[0].location'),json_extract(`lsr`.`scraped_data`,'$.locationName'),json_extract(`lsr`.`scraped_data`,'$[0].locationName'))) AS `lokasi_linkedin`,(case when ((`lsr`.`id` is null) or (json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName'))) is null)) then 0 when (lower(trim(coalesce(`da`.`nama`,`u`.`name`,''))) = lower(trim(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName')))))) then 1 when ((length(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName')))) >= 3) and ((lower(coalesce(`da`.`nama`,`u`.`name`,'')) like concat('%',lower(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName')))),'%')) or (lower(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName')))) like concat('%',lower(coalesce(`da`.`nama`,`u`.`name`,'')),'%')))) then 1 else 0 end) AS `is_nama_sesuai`,(case when ((`lsr`.`id` is null) or (json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName'))) is null)) then 'Belum Ada Data LinkedIn' when (lower(trim(coalesce(`da`.`nama`,`u`.`name`,''))) = lower(trim(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName')))))) then 'Nama Sesuai Eksak' when ((length(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName')))) >= 3) and ((lower(coalesce(`da`.`nama`,`u`.`name`,'')) like concat('%',lower(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName')))),'%')) or (lower(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.full_name'),json_extract(`lsr`.`scraped_data`,'$[0].full_name'),json_extract(`lsr`.`scraped_data`,'$.name'),json_extract(`lsr`.`scraped_data`,'$[0].name'),json_extract(`lsr`.`scraped_data`,'$.fullName'),json_extract(`lsr`.`scraped_data`,'$[0].fullName')))) like concat('%',lower(coalesce(`da`.`nama`,`u`.`name`,'')),'%')))) then 'Nama Sesuai Parsial' else 'Nama Berbeda (Perlu Verifikasi)' end) AS `keterangan_nama`,(case when (`lsr`.`status` = 'approved') then 1 when ((`b`.`perusahaan_id` is not null) and (json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.company_name'),json_extract(`lsr`.`scraped_data`,'$[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.current_company'),json_extract(`lsr`.`scraped_data`,'$[0].current_company'),json_extract(`lsr`.`scraped_data`,'$.companyName'),json_extract(`lsr`.`scraped_data`,'$[0].companyName'),json_extract(`lsr`.`scraped_data`,'$.company'),json_extract(`lsr`.`scraped_data`,'$[0].company'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company'))) is not null) and (lower(trim(coalesce(`c`.`nama_perusahaan`,''))) = lower(trim(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.company_name'),json_extract(`lsr`.`scraped_data`,'$[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.current_company'),json_extract(`lsr`.`scraped_data`,'$[0].current_company'),json_extract(`lsr`.`scraped_data`,'$.companyName'),json_extract(`lsr`.`scraped_data`,'$[0].companyName'),json_extract(`lsr`.`scraped_data`,'$.company'),json_extract(`lsr`.`scraped_data`,'$[0].company'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company'))))))) then 1 else 0 end) AS `is_terplot_ke_biodata`,(case when (`lsr`.`id` is null) then 'Belum Pernah Sync' when (`lsr`.`status` = 'approved') then 'Sudah Terplot (Approved)' when ((`lsr`.`status` = 'pending') and (((json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.company_name'),json_extract(`lsr`.`scraped_data`,'$[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.current_company'),json_extract(`lsr`.`scraped_data`,'$[0].current_company'),json_extract(`lsr`.`scraped_data`,'$.companyName'),json_extract(`lsr`.`scraped_data`,'$[0].companyName'),json_extract(`lsr`.`scraped_data`,'$.company'),json_extract(`lsr`.`scraped_data`,'$[0].company'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company'))) is not null) and (lower(trim(coalesce(`c`.`nama_perusahaan`,''))) = lower(trim(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.company_name'),json_extract(`lsr`.`scraped_data`,'$[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.current_company'),json_extract(`lsr`.`scraped_data`,'$[0].current_company'),json_extract(`lsr`.`scraped_data`,'$.companyName'),json_extract(`lsr`.`scraped_data`,'$[0].companyName'),json_extract(`lsr`.`scraped_data`,'$.company'),json_extract(`lsr`.`scraped_data`,'$[0].company'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company_name'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].company'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].company'))))))) or ((json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.job_title'),json_extract(`lsr`.`scraped_data`,'$[0].job_title'),json_extract(`lsr`.`scraped_data`,'$.current_job'),json_extract(`lsr`.`scraped_data`,'$[0].current_job'),json_extract(`lsr`.`scraped_data`,'$.title'),json_extract(`lsr`.`scraped_data`,'$[0].title'),json_extract(`lsr`.`scraped_data`,'$.position'),json_extract(`lsr`.`scraped_data`,'$[0].position'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].title'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].title'),json_extract(`lsr`.`scraped_data`,'$.headline'),json_extract(`lsr`.`scraped_data`,'$[0].headline'))) is not null) and (lower(trim(coalesce(`b`.`posisi_jabatan`,''))) = lower(trim(json_unquote(coalesce(json_extract(`lsr`.`scraped_data`,'$.job_title'),json_extract(`lsr`.`scraped_data`,'$[0].job_title'),json_extract(`lsr`.`scraped_data`,'$.current_job'),json_extract(`lsr`.`scraped_data`,'$[0].current_job'),json_extract(`lsr`.`scraped_data`,'$.title'),json_extract(`lsr`.`scraped_data`,'$[0].title'),json_extract(`lsr`.`scraped_data`,'$.position'),json_extract(`lsr`.`scraped_data`,'$[0].position'),json_extract(`lsr`.`scraped_data`,'$.experiences[0].title'),json_extract(`lsr`.`scraped_data`,'$[0].experiences[0].title'),json_extract(`lsr`.`scraped_data`,'$.headline'),json_extract(`lsr`.`scraped_data`,'$[0].headline'))))))))) then 'Data Sama / Siap Diplot' when (`lsr`.`status` = 'pending') then 'Menunggu Review (Pending)' when (`lsr`.`status` = 'rejected') then 'Ditolak (Rejected)' else 'Belum Terplot' end) AS `status_plot_label` from (((((((`biodata` `b` left join `users` `u` on((`b`.`user_id` = `u`.`id`))) left join `data_akademik` `da` on((`da`.`nim` = `b`.`nim`))) left join `prodi` `p` on((`b`.`prodi_id` = `p`.`id`))) left join `ref_fakultas` `f` on((`p`.`fakultas_id` = `f`.`id`))) left join `perusahaan` `c` on((`b`.`perusahaan_id` = `c`.`id`))) left join (select `l1`.`id` AS `id`,`l1`.`biodata_id` AS `biodata_id`,`l1`.`linkedin_url` AS `linkedin_url`,`l1`.`linkedin_username` AS `linkedin_username`,`l1`.`scraped_data` AS `scraped_data`,`l1`.`status` AS `status`,`l1`.`reviewed_by` AS `reviewed_by`,`l1`.`reviewed_at` AS `reviewed_at`,`l1`.`scraped_at` AS `scraped_at`,`l1`.`created_at` AS `created_at`,`l1`.`updated_at` AS `updated_at` from (`linkedin_sync_results` `l1` join (select `linkedin_sync_results`.`biodata_id` AS `biodata_id`,max(`linkedin_sync_results`.`id`) AS `max_id` from `linkedin_sync_results` group by `linkedin_sync_results`.`biodata_id`) `l2` on((`l1`.`id` = `l2`.`max_id`)))) `lsr` on((`lsr`.`biodata_id` = `b`.`id`))) left join `users` `u_rev` on((`lsr`.`reviewed_by` = `u_rev`.`id`)));
SQL
            );
        }
    }

    /**
     * Menghapus view 'v_alumni_linkedin_plot' saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_linkedin_plot');
    }
};
