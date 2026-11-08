<?php

/**
 * Konfigurasi indikator IKU S1.
 *
 * Nilai bobot dipisahkan dari service agar Biro 3 dapat mengonfirmasi atau
 * memperbarui matriks aturan tanpa memindahkan logika klasifikasi data.
 */
return [
    's1' => [
        'ump_multiplier' => 1.2,
        'slovin_error' => 0.023,
        'aee_status_target' => 60.0,
        'weight_confirmation_status' => 'needs_biro3_confirmation',
        'weights' => [
            'study_continuation' => 0.6,
            'before_graduation_above_ump' => 1.0,
            'before_graduation_below_ump' => 0.6,
            'employee_after_under_6_above_ump' => 1.0,
            'employee_after_6_under_12_above_ump' => 0.8,
            'employee_after_under_12_below_ump' => 0.6,
            'founder_after_under_6_above_ump' => 1.2,
            'founder_after_6_plus_above_ump' => 1.0,
            'founder_after_under_6_below_ump' => 0.8,
            'founder_after_6_plus_below_ump' => 0.6,
            'freelancer_after_under_6_above_ump' => 0.5,
            'freelancer_after_6_plus_above_ump' => 0.4,
            'freelancer_after_under_6_below_ump' => 0.3,
            'freelancer_after_6_plus_below_ump' => 0.2,
        ],
    ],
];
