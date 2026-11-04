<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

