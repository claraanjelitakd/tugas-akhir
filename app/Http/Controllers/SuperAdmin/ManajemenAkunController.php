<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\LogActivity;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ManajemenAkunController extends Controller
{
    /**
     * Tampilkan Halaman Manajemen Akun Pengguna
     */
    public function index(Request $request): Response
    {
        $roleFilter = $request->input('role', 'all');
        $prodiFilter = $request->input('prodi_id');
        $fakultasFilter = $request->input('fakultas_id');
        $search = $request->input('search');

        $query = User::with(['prodi.fakultas', 'fakultas', 'biodata.dataAkademik']);

        if ($roleFilter !== 'all') {
            $query->where('role', $roleFilter);
        }

        if ($prodiFilter) {
            $query->where('prodi_id', $prodiFilter);
        }

        if ($fakultasFilter) {
            // Admin Fakultas: memiliki fakultas_id langsung
            // Alumni / Admin Prodi: memiliki prodi_id yang berelasi ke fakultas_id
            $query->where(function ($q) use ($fakultasFilter) {
                $q->where('fakultas_id', $fakultasFilter)
                    ->orWhereHas('prodi', function ($pq) use ($fakultasFilter) {
                        $pq->where('fakultas_id', $fakultasFilter);
                    });
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();

        $unsyncedCandidates = DataAkademik::where('status_mahasiswa', 'Lulus')
            ->whereNotIn('nim', function ($q) {
                $q->select('nim')->from('biodata');
            })
            ->orderBy('nim', 'asc')
            ->get(['nim', 'nama', 'tahun_lulus', 'tahun_akademik_lulus', 'tanggal_lahir'])
            ->map(function ($row) {
                $parsed = Biodata::parseNim($row->nim);
                $prodi = $parsed ? Prodi::where('kode_prodi', $parsed['kode_prodi'])->first() : null;

                return [
                    'nim' => $row->nim,
                    'nama' => $row->nama ?: 'Mahasiswa UKDW',
                    'tahun_lulus' => $row->tahun_lulus ? (string) $row->tahun_lulus : '-',
                    'tahun_akademik_lulus' => $row->tahun_akademik_lulus ?: '-',
                    'tanggal_lahir' => $row->tanggal_lahir,
                    'prodi_nama' => $prodi?->nama_prodi ?: 'Tidak Terdefinisi',
                ];
            });

        $stats = [
            'total_users' => User::count(),
            'total_superadmin' => User::where('role', 'superadmin')->count(),
            'total_admin_biro3' => User::where('role', 'admin_biro3')->count(),
            'total_admin_fakultas' => User::where('role', 'admin_fakultas')->count(),
            'total_admin_prodi' => User::where('role', 'admin_prodi')->count(),
            'total_alumni' => User::where('role', 'alumni')->count(),
            'unsynced_alumni_count' => $unsyncedCandidates->count(),
        ];

        return Inertia::render('SuperAdmin/ManajemenAkun/Index', [
            'users' => $users,
            'prodis' => Prodi::orderBy('nama_prodi', 'asc')->get(),
            'fakultas' => RefFakultas::orderBy('nama_fakultas', 'asc')->get(),
            'unsynced_candidates' => $unsyncedCandidates,
            'filters' => [
                'role' => $roleFilter,
                'prodi_id' => $prodiFilter,
                'fakultas_id' => $fakultasFilter,
                'search' => $search,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Perbarui Data Pengguna (Edit Email, Nama, Username, Role, Prodi/Fakultas)
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'role' => 'required|string|in:superadmin,admin_biro3,admin_fakultas,admin_prodi,alumni',
            'prodi_id' => 'nullable|exists:prodi,id',
            'fakultas_id' => 'nullable|exists:ref_fakultas,id',
        ]);

        $oldValues = $user->only(['name', 'email', 'username', 'role', 'prodi_id', 'fakultas_id']);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'role' => $request->role,
            'prodi_id' => $request->prodi_id,
            'fakultas_id' => $request->fakultas_id,
        ]);

        if ($user->biodata) {
            $user->biodata->update([
                'nama' => $request->name,
                'email_pribadi' => $request->email,
            ]);
        }

        // Catat Audit Trail ke tabel log_activities
        LogActivity::record(
            'UPDATE_USER_ACCOUNT',
            "Super Admin memperbarui data akun {$user->name} (@{$user->username}). Email diatur ke {$request->email}.",
            $user,
            $oldValues,
            $user->only(['name', 'email', 'username', 'role', 'prodi_id', 'fakultas_id'])
        );

        return back()->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    /**
     * Reset Password Otomatis ke Tanggal Lahir (ddmmyyyy) atau Password Default,
     * sekaligus Mengirimkan Email Konfirmasi Pemulihan ke Email Terdaftar
     */
    public function resetToDefaultAndNotify(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role !== 'alumni') {
            return back()->withErrors([
                'reset' => 'Reset kredensial tanggal lahir hanya tersedia untuk akun alumni.',
            ]);
        }

        // 1. Cari tanggal lahir (ddmmyyyy) jika alumni
        $newPasswordPlain = null;
        if ($user->biodata && $user->biodata->tanggal_lahir) {
            $newPasswordPlain = Carbon::parse($user->biodata->tanggal_lahir)->format('dmY');
        } elseif ($user->biodata && $user->biodata->dataAkademik && $user->biodata->dataAkademik->tanggal_lahir) {
            $newPasswordPlain = Carbon::parse($user->biodata->dataAkademik->tanggal_lahir)->format('dmY');
        } else {
            $dataAkad = DataAkademik::where('nim', $user->username)->first();
            if ($dataAkad && $dataAkad->tanggal_lahir) {
                $newPasswordPlain = Carbon::parse($dataAkad->tanggal_lahir)->format('dmY');
            }
        }

        if (! $newPasswordPlain) {
            return back()->withErrors([
                'reset' => 'Tanggal lahir alumni belum tersedia. Gunakan alur lupa password melalui email.',
            ]);
        }

        // 2. Reset password & set wajib ganti password awal (must_change_password = true)
        $user->password = Hash::make($newPasswordPlain);
        $user->must_change_password = true;
        $user->save();

        // Catat Audit Trail ke tabel log_activities
        LogActivity::record(
            'RESET_PASSWORD_DOB',
            "Super Admin mereset kredensial awal akun {$user->name} (@{$user->username}).",
            $user
        );

        return back()->with('success', "Kredensial awal untuk {$user->name} berhasil direset. Pengguna wajib mengganti password setelah login.");
    }

    /**
     * Sinkronkan Data Akademik Mahasiswa Lulusan menjadi Akun Pengguna (Alumni) & Profil Biodata
     *
     * @return RedirectResponse
     */
    public function syncAlumniAccounts(Request $request)
    {
        $selectedNims = $request->input('selected_nims');

        $query = DataAkademik::where('status_mahasiswa', 'Lulus')
            ->whereNotIn('nim', function ($q) {
                $q->select('nim')->from('biodata');
            });

        if (is_array($selectedNims) && ! empty($selectedNims)) {
            $query->whereIn('nim', $selectedNims);
        }

        $unsyncedRecords = $query->get();

        if ($unsyncedRecords->isEmpty()) {
            return back()->with('info', 'Tidak ada data mahasiswa lulusan yang perlu disinkronkan.');
        }

        $syncedCount = 0;

        DB::transaction(function () use ($unsyncedRecords, &$syncedCount) {
            foreach ($unsyncedRecords as $record) {
                $nim = trim((string) $record->nim);
                if (empty($nim)) {
                    continue;
                }

                // Tentukan Prodi dan Fakultas dari NIM (2 digit awal)
                $parsed = Biodata::parseNim($nim);
                $prodi = null;
                if ($parsed) {
                    $prodi = Prodi::where('kode_prodi', $parsed['kode_prodi'])->first();
                }

                $prodiId = $prodi?->id;
                $fakultasId = $prodi?->fakultas_id;

                // Tentukan password default dari tanggal lahir (ddmmyyyy)
                $plainPassword = 'password123';
                if ($record->tanggal_lahir) {
                    $plainPassword = Carbon::parse($record->tanggal_lahir)->format('dmY');
                }

                $email = $record->email_students ?: ($nim.'@students.ukdw.ac.id');
                // Pastikan email unik jika sudah ada user lain yang menggunakan email sama
                if (User::where('email', $email)->exists()) {
                    $email = $nim.'.'.time().'@students.ukdw.ac.id';
                }

                // Cari atau buat User
                $user = User::where('username', $nim)->first();
                if (! $user) {
                    $user = User::create([
                        'name' => $record->nama ?: 'Alumni UKDW',
                        'username' => $nim,
                        'email' => $email,
                        'password' => Hash::make($plainPassword),
                        'role' => 'alumni',
                        'must_change_password' => true,
                        'prodi_id' => $prodiId,
                        'fakultas_id' => $fakultasId,
                    ]);
                } else {
                    $user->update([
                        'role' => 'alumni',
                        'prodi_id' => $prodiId,
                        'fakultas_id' => $fakultasId,
                    ]);
                }

                // Buat profil di Biodata
                Biodata::updateOrCreate(
                    ['nim' => $nim],
                    [
                        'user_id' => $user->id,
                        'prodi_id' => $prodiId,
                        'email_pribadi' => $user->email,
                    ]
                );

                $syncedCount++;
            }
        });

        // Catat Audit Trail
        LogActivity::record(
            'SYNC_ALUMNI_ACCOUNTS',
            "Super Admin menyinkronkan {$syncedCount} akun alumni dari data akademik."
        );

        return back()->with('success', "Berhasil menyinkronkan {$syncedCount} akun alumni baru dari data akademik.");
    }
}
