<?php

namespace Tests\Feature\Otentikasi;

use Tests\TestCase;

class LupaKataSandiTest extends TestCase
{
    public function test_guest_can_open_forgot_password_page(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Otentikasi/ForgotPassword'));
    }
}
