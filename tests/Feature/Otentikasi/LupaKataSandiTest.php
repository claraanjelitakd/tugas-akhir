<?php

namespace Tests\Feature\Otentikasi;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LupaKataSandiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_forgot_password_page(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Otentikasi/ForgotPassword'));
    }

    public function test_submitting_valid_account_sends_reset_link_and_flashes_success(): void
    {
        Mail::fake();

        $user = User::create([
            'username' => '72220999',
            'name' => 'Tester Lupa Sandi',
            'email' => 'tester.lupa@students.ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        $response = $this->post(route('password.email'), [
            'username_atau_email' => '72220999',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('status');

        Mail::assertQueued(ResetPasswordMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_submitting_nonexistent_account_flashes_not_found_error(): void
    {
        $response = $this->post(route('password.email'), [
            'username_atau_email' => 'tidakada999',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('username_atau_email');
        $response->assertSessionHas('error');
    }

    public function test_submitting_too_many_requests_flashes_rate_limit_error(): void
    {
        RateLimiter::clear('forgot-password|72220888|127.0.0.1');

        $user = User::create([
            'username' => '72220888',
            'name' => 'Tester Rate Limit',
            'email' => 'tester.rate@students.ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        // Kirim 3 kali (batas maksimum)
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('password.email'), [
                'username_atau_email' => '72220888',
            ]);
        }

        // Percobaan ke-4 harus terblokir oleh rate limiter
        $response = $this->post(route('password.email'), [
            'username_atau_email' => '72220888',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('username_atau_email');
        $response->assertSessionHas('error');
    }
}
