<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'role', 'kode_prodi', 'kode_fakultas', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     * Mengatur tipe data atribut saat diakses atau disimpan.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean', // Pastikan bertipe boolean
            'password' => 'hashed', // Password otomatis di-hash
        ];
    }

    /**
     * Program studi yang dinaungi oleh user (khusus role admin_prodi).
     */
    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'kode_prodi', 'kode_prodi');
    }

    /**
     * Fakultas yang dinaungi oleh user (khusus role admin_fakultas).
     */
    public function fakultas()
    {
        return $this->belongsTo(RefFakultas::class, 'kode_fakultas', 'kode_fakultas');
    }

    /**
     * Get the biodata record associated with the user.
     */
    public function biodata()
    {
        return $this->hasOne(Biodata::class);
    }

    /**
     * Alias for backward compatibility.
     */
    public function alumni()
    {
        return $this->biodata();
    }

    /**
     * Accessor untuk nama pengguna
     */
    public function getNameAttribute($value)
    {
        return $value ?? $this->biodata?->dataAkademik?->nama;
    }

    /**
     * Accessor untuk email pengguna
     * Menggunakan email pada tabel users atau fallback ke email_pribadi di biodata / data akademik
     */
    public function getEmailAttribute($value)
    {
        return $this->biodata?->email_pribadi ?? $value;
    }
}
