<?php

namespace Tests\Feature;

use App\Mail\PengingatKuesionerAlumniMail;
use App\Mail\ResetPasswordMail;
use App\Mail\UndanganEvaluasiAtasanMail;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\User;
use Tests\TestCase;

/**
 * Memastikan seluruh template email memakai layout responsif dan identitas no-reply.
 */
class EmailTemplateTest extends TestCase
{
    /**
     * Memastikan email reset, pengingat, dan evaluasi nyaman digunakan di desktop serta mobile.
     */
    public function test_all_email_templates_use_responsive_layout_and_no_reply_footer(): void
    {
        $user = User::make([
            'name' => 'Alumni Template',
            'username' => '72240001',
        ]);

        $biodata = Biodata::make([
            'nama' => 'Alumni Template',
            'nim' => '72240001',
        ]);
        $biodata->setRelation('dataAkademik', null);
        $biodata->setRelation('prodi', null);
        $biodata->setRelation('perusahaan', null);
        $evaluation = EvaluasiAtasan::make(['token' => 'template-token']);

        $mailables = [
            new ResetPasswordMail($user, 'https://localhost/reset-password/template-token'),
            new PengingatKuesionerAlumniMail($biodata),
            new UndanganEvaluasiAtasanMail($biodata, $evaluation),
        ];

        foreach ($mailables as $mailable) {
            $html = $mailable->render();

            $this->assertStringContainsString('name="viewport"', $html);
            $this->assertStringContainsString('@media screen and (max-width: 600px)', $html);
            $this->assertStringContainsString('email-button', $html);
            $this->assertStringContainsString('Pesan ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.', $html);
        }
    }
}
