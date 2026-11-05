<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Memverifikasi akses detail alumni dari analitik lintas fakultas.
 */
class AdminFakultasAnalyticsDetailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admin Fakultas hanya dapat membuka detail lintas fakultas melalui endpoint analitik baca-saja.
     */
    public function test_admin_fakultas_can_view_cross_faculty_alumni_as_read_only_from_analytics(): void
    {
        $fakultasA = RefFakultas::create([
            'kode_fakultas' => 'FA',
            'nama_fakultas' => 'Fakultas A',
        ]);
        $fakultasB = RefFakultas::create([
            'kode_fakultas' => 'FB',
            'nama_fakultas' => 'Fakultas B',
        ]);
        $prodiB = Prodi::create([
            'fakultas_id' => $fakultasB->id,
            'kode_prodi' => 'PB',
            'nama_prodi' => 'Program Studi B',
        ]);
        $adminFakultasA = User::create([
            'username' => 'admin-fakultas-a',
            'name' => 'Admin Fakultas A',
            'password' => Hash::make('password123'),
            'role' => 'admin_fakultas',
            'fakultas_id' => $fakultasA->id,
            'must_change_password' => false,
        ]);
        $alumniUser = User::create([
            'username' => 'alumni-fakultas-b',
            'name' => 'Alumni Fakultas B',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $prodiB->id,
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => 'PB000001',
            'nama' => 'Alumni Fakultas B',
            'email_pribadi' => 'alumni-b@example.test',
            'tanggal_lahir' => '2000-01-01',
            'jenis_kelamin' => 'Laki-laki',
        ]);
        $alumni = Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => 'PB000001',
            'nama' => 'Alumni Fakultas B',
            'prodi_id' => $prodiB->id,
        ]);

        $this->actingAs($adminFakultasA)
            ->get(route('fakultas.alumni.show', $alumni->id))
            ->assertNotFound();

        $this->actingAs($adminFakultasA)
            ->get(route('fakultas.analytics.alumni.show', $alumni->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminFakultas/Alumni/Show')
                ->where('readonly', true)
                ->where('alumni.id', $alumni->id)
            );

        $this->actingAs($adminFakultasA)
            ->post(route('fakultas.alumni.profile.update', $alumni->id), [
                'nama' => 'Tidak boleh diubah lintas fakultas',
            ])
            ->assertNotFound();
    }
}
