<?php

namespace Database\Factories;

use App\Models\Propinsi;
use App\Models\Ump;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ump>
 */
class UmpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_provinsi' => Propinsi::inRandomOrder()->value('kode_provinsi') ?? '31',
            'tahun' => (int) now()->year,
            'besaran' => fake()->numberBetween(2500000, 5500000),
            'catatan' => null,
        ];
    }
}
