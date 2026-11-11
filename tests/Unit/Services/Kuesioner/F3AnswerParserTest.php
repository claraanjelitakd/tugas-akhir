<?php

namespace Tests\Unit\Services\Kuesioner;

use App\Services\Kuesioner\F3AnswerParser;
use PHPUnit\Framework\TestCase;

/**
 * Memastikan jawaban teks F3 dinormalisasi menjadi fase dan angka bulan.
 */
class F3AnswerParserTest extends TestCase
{
    /**
     * Format legacy sebelum lulus tetap menghasilkan angka bulan.
     */
    public function test_before_graduation_legacy_answer_returns_numeric_months(): void
    {
        $result = (new F3AnswerParser)->parse('Sebelum lulus ... bulan: 3');

        $this->assertSame('before_graduation', $result['phase_key']);
        $this->assertSame(3.0, $result['months']);
        $this->assertSame('Sebelum lulus', $result['label']);
    }

    /**
     * Jawaban setelah lulus dipetakan ke rentang sesuai angka bulan.
     */
    public function test_after_graduation_answer_returns_canonical_wait_range(): void
    {
        $parser = new F3AnswerParser;

        $underSix = $parser->parse('Setelah lulus ... bulan: 2');
        $sixToTwelve = $parser->parse('Sesudah lulus ... bulan: 6');
        $overTwelve = $parser->parse('Setelah lulus ... bulan: 13');

        $this->assertSame('after_under_6', $underSix['segment_key']);
        $this->assertSame('after_6_to_12', $sixToTwelve['segment_key']);
        $this->assertSame('after_over_12', $overTwelve['segment_key']);
    }

    /**
     * Jawaban non-numerik tidak dipaksa menjadi masa tunggu palsu.
     */
    public function test_non_timing_answer_does_not_create_numeric_wait_months(): void
    {
        $result = (new F3AnswerParser)->parse('Saya tidak mencari kerja');

        $this->assertSame('not_searching', $result['phase_key']);
        $this->assertNull($result['months']);
    }
}
