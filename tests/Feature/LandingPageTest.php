<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pengujian Halaman Landing Page (Beranda Tamu).
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memastikan halaman landing page dapat diakses secara publik tanpa error.
     */
    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tamu/Beranda')
            ->has('stats')
            ->has('featuredAlumni')
            ->has('alumniWilayah')
            ->has('alumniList')
            ->has('allProvinsi')
            ->has('allKabupaten')
        );
    }

    /**
     * Memastikan pengguna yang sudah login otomatis dialihkan ke dashboard sesuai perannya.
     */
    public function test_authenticated_user_redirected_to_dashboard(): void
    {
        $user = User::create([
            'username' => 'alumni_test',
            'name' => 'Alumni Test',
            'email' => 'alumni.test@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('alumni.dashboard'));
    }
}
