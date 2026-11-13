<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\LogActivity;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use App\Services\Otentikasi\PasswordResetNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Mengelola akun pengguna, sinkronisasi alumni, dan pemulihan kata sandi.
 */
class ManajemenAkunController extends Controller
{
    /**
     * ID baris tunggal pengaturan permintaan sinkronisasi akun.
     */
    private const ACCOUNT_SYNC_SETTING_ID = 1;

    /**
     * Role yang dapat menerima tautan reset dari Manajemen Akun Super Admin.
     *
     * @var array<int, string>
     */
    private const RESETTABLE_ROLES = [
        'admin_biro3',
        'admin_fakultas',
        'admin_prodi',
        'alumni',
    ];

    /**
     * Menyiapkan service pengiriman tautan reset kata sandi.
     *
     * @param  PasswordResetNotificationService  $passwordResetNotificationService  Service reset kata sandi terpusat.
     */
    public function __construct(
        private PasswordResetNotificationService $passwordResetNotificationService
    ) {}

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
            ->get(['nim', 'nama', 'email_pribadi', 'tahun_lulus', 'tahun_akademik_lulus', 'tanggal_lahir'])
            ->map(function ($row) {
                $parsed = Biodata::parseNim($row->nim);
                $prodi = $parsed ? Prodi::where('kode_prodi', $parsed['kode_prodi'])->first() : null;

                return [
                    'nim' => $row->nim,
                    'nama' => $row->nama ?: 'Mahasiswa UKDW',
                    'email_pribadi' => $row->email_pribadi,
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
            'account_sync' => $this->getAccountSyncSetting(),
        ]);
    }

    /**
     * Membuka atau menutup penerimaan permintaan pembuatan akun dari Data Akademik.
     *
     * @param  Request  $request  Request yang berisi status buka/tutup.
     * @return RedirectResponse Redirect kembali ke halaman manajemen akun.
     */
    public function updateSyncAvailability(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_open' => ['required', 'boolean'],
        ]);

        $isOpen = (bool) $validated['is_open'];

        DB::table('account_sync_settings')->updateOrInsert(
            ['id' => self::ACCOUNT_SYNC_SETTING_ID],
            [
                'is_open' => $isOpen,
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        LogActivity::record(
            'UPDATE_ACCOUNT_SYNC_AVAILABILITY',
            'Super Admin '.($isOpen ? 'membuka' : 'menutup').' permintaan pembuatan akun dari Data Akademik.'
        );

        return back()->with('success', $isOpen
            ? 'Permintaan pembuatan akun dari Data Akademik telah dibuka.'
            : 'Permintaan pembuatan akun dari Data Akademik telah ditutup.');
    }

    /**
     * Perbarui Data Pengguna (Edit Email, Nama, Username, Role, Prodi/Fakultas)
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'role' => 'required|string|in:superadmin,admin_biro3,admin_fakultas,admin_prodi,alumni',
            'prodi_id' => 'nullable|exists:prodi,id',
            'fakultas_id' => [
                'nullable',
                'exists:ref_fakultas,id',
                Rule::requiredIf(fn (): bool => $request->input('role') === 'admin_fakultas'),
            ],
        ]);

        $oldValues = $user->only(['name', 'email', 'username', 'role', 'prodi_id', 'fakultas_id']);

        $role = $validated['role'];
        $prodiId = in_array($role, ['admin_prodi', 'alumni'], true)
            ? ($validated['prodi_id'] ?? null)
            : null;
        $fakultasId = $role === 'admin_fakultas'
            ? $validated['fakultas_id']
            : ($prodiId ? Prodi::find($prodiId)?->fakultas_id : null);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
            'role' => $role,
            'prodi_id' => $prodiId,
            'fakultas_id' => $fakultasId,
        ]);

        if ($user->biodata) {
            $user->biodata->update([
                'nama' => $validated['name'],
                'email_pribadi' => $validated['email'],
            ]);
        }

        // Catat Audit Trail ke tabel log_activities
        LogActivity::record(
            'UPDATE_USER_ACCOUNT',
            "Super Admin memperbarui data akun {$user->name} (@{$user->username}). Email diatur ke {$validated['email']}.",
            $user,
            $oldValues,
            $user->only(['name', 'email', 'username', 'role', 'prodi_id', 'fakultas_id'])
        );

        return back()->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    /**
     * Mengirim tautan reset kata sandi ke akun admin atau alumni yang dipilih.
     *
     * Password baru ditetapkan oleh pemilik akun setelah membuka tautan email,
     * sehingga password tidak pernah ditampilkan atau dikirim sebagai teks biasa.
     *
     * @param  int  $id  ID akun yang menerima tautan reset.
     * @return RedirectResponse Redirect kembali dengan status keberhasilan atau error.
     */
    public function sendPasswordResetLink(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! in_array($user->role, self::RESETTABLE_ROLES, true)) {
            return back()->withErrors([
                'reset' => 'Akun ini tidak dapat menerima tautan reset dari Manajemen Akun.',
            ]);
        }

        try {
            $email = $this->passwordResetNotificationService->send($user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'reset' => 'Tautan reset gagal dikirim. Periksa email akun dan konfigurasi mail server.',
            ]);
        }

        // Catat Audit Trail ke tabel log_activities
        LogActivity::record(
            'REQUEST_PASSWORD_RESET',
            "Super Admin mengirim tautan reset kata sandi untuk akun {$user->name} (@{$user->username}).",
            $user
        );

        return back()->with('success', "Tautan reset kata sandi untuk {$user->name} telah dikirim ke {$email}.");
    }

    /**
     * Sinkronkan Data Akademik Mahasiswa Lulusan menjadi Akun Pengguna (Alumni) & Profil Biodata
     *
     * @return RedirectResponse
     */
    public function syncAlumniAccounts(Request $request): RedirectResponse
    {
        if (! $this->getAccountSyncSetting()['is_open']) {
            return back()->withErrors([
                'sync' => 'Permintaan pembuatan akun sedang ditutup. Buka kembali sinkronisasi sebelum memproses Data Akademik.',
            ]);
        }

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

                $sourceEmail = trim((string) $record->email_pribadi);
                $email = filter_var($sourceEmail, FILTER_VALIDATE_EMAIL)
                    ? $sourceEmail
                    : ($nim.'@students.ukdw.ac.id');
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
                        'email' => $email,
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
                        'email_pribadi' => $email,
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

    /**
     * Mengambil status sinkronisasi akun dan membuat baris default jika diperlukan.
     *
     * @return array{is_open: bool, updated_at: string|null} Status permintaan akun.
     */
    private function getAccountSyncSetting(): array
    {
        $setting = DB::table('account_sync_settings')
            ->where('id', self::ACCOUNT_SYNC_SETTING_ID)
            ->first();

        if (! $setting) {
            DB::table('account_sync_settings')->insert([
                'id' => self::ACCOUNT_SYNC_SETTING_ID,
                'is_open' => true,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $setting = DB::table('account_sync_settings')
                ->where('id', self::ACCOUNT_SYNC_SETTING_ID)
                ->first();
        }

        return [
            'is_open' => (bool) $setting->is_open,
            'updated_at' => $setting->updated_at,
        ];
    }
}
