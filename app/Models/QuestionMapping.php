<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model QuestionMapping
 *
 * Terhubung ke database VIEW `v_question_mappings` yang memetakan kolom profil alumni
 * ke pertanyaan instrumen kuesioner.
 */
class QuestionMapping extends Model
{
    use HasFactory;

    protected $table = 'v_question_mappings';

    public $timestamps = false;

    protected $fillable = ['table_name', 'column_name', 'question_id', 'kode_pertanyaan'];

    protected $appends = ['code'];

    public function getCodeAttribute()
    {
        return $this->attributes['kode_pertanyaan'] ?? null;
    }

    public function subpertanyaan()
    {
        return $this->belongsTo(KuesionerPertanyaan::class, 'question_id');
    }

    public function question()
    {
        return $this->subpertanyaan();
    }
}
