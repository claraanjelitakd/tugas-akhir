<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Model LogActivity (tabel: log_activities)
 *
 * Mencatat rekam jejak (audit trail) setiap aksi penting dalam sistem,
 * terutama proses verifikasi/ACC/ganti/tolak perusahaan, reset password,
 * serta perubahan akun pengguna.
 */
class LogActivity extends Model
{
    use HasFactory;

    protected $table = 'log_activities';

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Relasi ke Pengguna (Pelaku aksi)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper statis untuk mempermudah pencatatan log di seluruh controller / service.
     * Mendukung fleksibilitas pemanggilan baik model/subject maupun key/details.
     */
    public static function record(
        string $action,
        string $description = '',
        mixed $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $model = null,
        mixed $modelId = null,
        ?array $details = null
    ): self {
        $modelType = $model;
        if (! $modelType && $subject instanceof Model) {
            $modelType = get_class($subject);
        }

        $resolvedModelId = $modelId;
        if (! $resolvedModelId && $subject instanceof Model) {
            $resolvedModelId = $subject->getKey();
        }

        if ($resolvedModelId !== null) {
            $resolvedModelId = (string) $resolvedModelId;
        }

        $recordedNewValues = $newValues ?? $details;

        return static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $resolvedModelId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $recordedNewValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
