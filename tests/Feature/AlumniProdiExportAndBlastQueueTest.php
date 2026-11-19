<?php

namespace Tests\Feature;

use App\Jobs\SendBlastReminderEmailJob;
use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\LogActivity;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * AlumniProdiExportAndBlastQueueTest
 *
 * Menguji fungsionalitas ekspor Excel multi-sheet per prodi dan pengiriman email pengingat massal via antrean job.
 */
class AlumniProdiExportAndBlastQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminProdiUser;

    protected User $adminBiro3User;

    protected Prodi $prodi;

    protected Biodata $alumniBelumSelesai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $this->adminProdiUser = User::create([
            'username' => 'admin_prodi_si',
            'name' => 'Admin Prodi SI',
            'role' => 'admin_prodi',
            'prodi_id' => $this->prodi->id,
            'password' => Hash::make('password123'),
            'must_change_password' => false,
        ]);

        $this->adminBiro3User = User::create([
            'username' => 'admin_biro3',
            'name' => 'Admin Biro 3',
            'role' => 'admin_biro3',
            'password' => Hash::make('password123'),
            'must_change_password' => false,
        ]);

        $alumniUser = User::create([
            'username' => '72200002',
            'name' => 'Alumni Belum Selesai',
            'email' => 'alumni.target@example.com',
            'role' => 'alumni',
            'password' => Hash::make('password123'),
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200002',
            'nama' => 'Alumni Belum Selesai',
            'prodi_id' => $this->prodi->id,
            'tahun_lulus' => '2024',
            'tahun_akademik_lulus' => 'Genap 2023/2024',
        ]);

        $this->alumniBelumSelesai = Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => '72200002',
            'prodi_id' => $this->prodi->id,
            'email_pribadi' => 'alumni.target@example.com',
        ]);
    }

    /**
     * Memastikan Admin Prodi dapat mengunduh berkas Excel multi-sheet prodinya.
     */
    public function test_admin_prodi_can_export_excel_multi_sheet(): void
    {
        $response = $this->actingAs($this->adminProdiUser)
            ->get('/prodi/alumni/export-excel-prodi?tahun=2024');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.ms-excel; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
    }

    /**
     * Memastikan Admin Biro 3 dapat mengunduh berkas Excel multi-sheet dengan menyertakan parameter prodi_id.
     */
    public function test_admin_biro3_can_export_excel_multi_sheet(): void
    {
        $response = $this->actingAs($this->adminBiro3User)
            ->get('/biro3/alumni/export-excel-prodi?prodi_id='.$this->prodi->id.'&tahun=2024');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.ms-excel; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    /**
     * Memastikan rute legacy export-zip otomatis mengembalikan berkas Excel multi-sheet tunggal (bukan arsip ZIP).
     */
    public function test_legacy_export_zip_route_returns_single_excel_file(): void
    {
        $response = $this->actingAs($this->adminBiro3User)
            ->get('/biro3/alumni/export-zip?prodi_id='.$this->prodi->id.'&tahun=2024');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.ms-excel; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xls', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('.zip', (string) $response->headers->get('Content-Disposition'));
    }

    /**
     * Memastikan eksekusi blast email mendispatch SendBlastReminderEmailJob ke antrean.
     */
    public function test_blast_email_dispatches_queue_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->adminProdiUser)
            ->post('/prodi/alumni/blast-email', [
                'tahun' => '2024',
            ]);

        $response->assertSessionHas('success');
        Queue::assertPushed(SendBlastReminderEmailJob::class, function (SendBlastReminderEmailJob $job) {
            return in_array($this->alumniBelumSelesai->id, $job->biodataIds, true)
                && $job->initiatorUserId === $this->adminProdiUser->id;
        });
    }

    /**
     * Memastikan SendBlastReminderEmailJob memproses pengiriman email dan mencatat audit log.
     */
    public function test_blast_email_job_handles_and_records_log(): void
    {
        Mail::fake();

        $job = new SendBlastReminderEmailJob(
            [$this->alumniBelumSelesai->id],
            $this->adminProdiUser->id,
            ['tahun' => '2024', 'prodi_id' => $this->prodi->id]
        );

        $job->handle();

        Mail::assertQueued(PengingatKuesionerAlumniMail::class);

        $log = LogActivity::where('action', 'blast_email_pengingat')->latest('id')->first();
        $this->assertNotNull($log, 'Log aktivitas blast email wajib tercatat.');
        $this->assertEquals($this->adminProdiUser->id, $log->user_id);
    }
}
