<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LinkedinSyncResult
 *
 * Model staging dan histori sinkronisasi profil LinkedIn via Apify.
 * Memastikan hasil scraping tidak langsung mengubah data utama sebelum diapprove oleh Super Admin.
 *
 * @property int $id
 * @property int $biodata_id
 * @property string $linkedin_url
 * @property string|null $linkedin_username
 * @property array $scraped_data
 * @property string $status 'pending'|'approved'|'rejected'
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $scraped_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LinkedinSyncResult extends Model
{
    use HasFactory;

    protected $table = 'linkedin_sync_results';

    protected $fillable = [
        'biodata_id',
        'linkedin_url',
        'linkedin_username',
        'scraped_data',
        'status',
        'reviewed_by',
        'reviewed_at',
        'scraped_at',
    ];

    protected $casts = [
        'scraped_data' => 'array',
        'reviewed_at' => 'datetime',
        'scraped_at' => 'datetime',
    ];

    protected $appends = [
        'preview',
    ];

    /**
     * Accessor untuk attribute 'preview'.
     *
     * @return array<string, mixed>
     */
    public function getPreviewAttribute(): array
    {
        return $this->getPreviewData();
    }

    /**
     * Relasi ke data alumni (Biodata).
     */
    public function biodata(): BelongsTo
    {
        return $this->belongsTo(Biodata::class, 'biodata_id');
    }

    /**
     * Relasi ke user reviewer (Super Admin).
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Mendapatkan objek profil tunggal dari scraped_data (baik berupa array dataset maupun object).
     */
    public function getProfileItem(): array
    {
        $raw = $this->scraped_data ?? [];

        if (is_array($raw) && isset($raw[0]) && is_array($raw[0])) {
            return $raw[0];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Mengekstrak field pratinjau (preview) yang benar-benar tersedia tanpa data palsu.
     * Mendukung format Apify data_forge_org~linkedin-scraper dan skema standar.
     *
     * @return array{
     *     profile_url: ?string,
     *     name: ?string,
     *     headline: ?string,
     *     about: ?string,
     *     location: ?string,
     *     profile_picture: ?string,
     *     experience: array,
     *     education: array,
     *     skills: array,
     *     current_job: ?string,
     *     current_company: ?string,
     *     current_company_location: ?string,
     *     current_company_url: ?string,
     *     handle: ?string,
     *     followers: ?int,
     *     connections: ?int
     * }
     */
    public function getPreviewData(): array
    {
        $item = $this->getProfileItem();

        // 1. Profile URL
        $profileUrl = $item['li_profile_url']
            ?? $item['source_url']
            ?? $item['profileUrl']
            ?? $item['url']
            ?? $item['linkedinUrl']
            ?? $item['input']
            ?? $this->linkedin_url;

        // 2. Foto Profil
        $profilePicture = $item['li_profile_image_url']
            ?? $item['profilePicture']
            ?? $item['profile_picture']
            ?? $item['picture_url']
            ?? null;

        // 3. Nama Lengkap
        $name = null;
        if (! empty($item['full_name'])) {
            $name = trim($item['full_name']);
        } elseif (! empty($item['fullName'])) {
            $name = trim($item['fullName']);
        } elseif (! empty($item['name'])) {
            $name = trim($item['name']);
        } elseif (! empty($item['first_name']) || ! empty($item['last_name'])) {
            $name = trim(($item['first_name'] ?? '').' '.($item['last_name'] ?? ''));
        } elseif (! empty($item['firstName']) || ! empty($item['lastName'])) {
            $name = trim(($item['firstName'] ?? '').' '.($item['lastName'] ?? ''));
        }

        // 4. Headline
        $headline = $item['headline'] ?? $item['title'] ?? null;

        // 5. About / Summary
        $about = $item['about'] ?? $item['summary'] ?? $item['description'] ?? null;

        // 6. Lokasi
        $location = null;
        if (! empty($item['location'])) {
            $location = is_array($item['location']) ? ($item['location']['name'] ?? null) : $item['location'];
        } elseif (! empty($item['locationName'])) {
            $location = $item['locationName'];
        } elseif (! empty($item['geoCountryName'])) {
            $location = $item['geoCountryName'];
        }

        // 7. Pengalaman Kerja (Experience / Experiences)
        $rawExp = $item['experiences'] ?? $item['experience'] ?? $item['positions'] ?? $item['position'] ?? [];
        $experienceList = [];
        $currentJob = null;
        $currentCompany = null;
        $currentCompanyLocation = null;
        $currentCompanyUrl = null;

        $activeJob = null;
        $activeCompany = null;
        $activeLoc = null;
        $activeUrl = null;

        $firstJob = null;
        $firstCompany = null;
        $firstLoc = null;
        $firstUrl = null;

        if (is_array($rawExp)) {
            foreach ($rawExp as $pos) {
                if (! is_array($pos)) {
                    continue;
                }

                $jobTitle = $pos['title'] ?? $pos['position'] ?? $pos['jobTitle'] ?? $pos['role'] ?? null;
                $company = $pos['company_name'] ?? $pos['companyName'] ?? $pos['company'] ?? $pos['name'] ?? null;
                $loc = $pos['location'] ?? $pos['locationName'] ?? null;
                $compUrl = $pos['li_company_url'] ?? $pos['company_url'] ?? $pos['companyUrl'] ?? null;
                $dateStr = $pos['date'] ?? '';

                $isCurrent = (isset($pos['isCurrent']) && $pos['isCurrent'] === true)
                    || (is_string($dateStr) && (str_contains(strtolower($dateStr), 'present') || str_contains(strtolower($dateStr), 'saat ini')))
                    || (array_key_exists('endDate', $pos) && (is_null($pos['endDate']) || $pos['endDate'] === 'Present' || $pos['endDate'] === 'Saat ini'));

                $experienceList[] = [
                    'position' => $jobTitle,
                    'company' => $company,
                    'location' => $loc,
                    'date' => ! empty($dateStr) ? $dateStr : null,
                    'time_period' => $pos['job_time_period'] ?? null,
                    'company_url' => $compUrl,
                    'company_logo' => $pos['company_logo_url'] ?? null,
                    'start_date' => $pos['startDate'] ?? $pos['startYear'] ?? null,
                    'end_date' => $pos['endDate'] ?? $pos['endYear'] ?? null,
                    'is_current' => $isCurrent,
                    'description' => $pos['description'] ?? null,
                ];

                if ($isCurrent && ! $activeJob && $jobTitle) {
                    $activeJob = $jobTitle;
                    $activeCompany = $company;
                    $activeLoc = $loc;
                    $activeUrl = $compUrl;
                }

                if (! $firstJob && $jobTitle) {
                    $firstJob = $jobTitle;
                    $firstCompany = $company;
                    $firstLoc = $loc;
                    $firstUrl = $compUrl;
                }
            }
        }

        // Tentukan Pekerjaan & Perusahaan Saat Ini (Current):
        // 1. Prioritaskan entri pengalaman kerja yang berstatus aktif (is_current)
        if ($activeJob || $activeCompany) {
            $currentJob = $activeJob;
            $currentCompany = $activeCompany;
            $currentCompanyLocation = $activeLoc;
            $currentCompanyUrl = $activeUrl;
        }

        // 2. Jika tidak ada yang aktif di experiences, gunakan root metadata Apify
        if (! $currentCompany && ! empty($item['company_name'])) {
            $currentCompany = trim($item['company_name']);
            $currentCompanyUrl = ! empty($item['li_company_url']) ? trim($item['li_company_url']) : null;
        }
        if (! $currentJob && ! empty($item['job_title'])) {
            $currentJob = trim($item['job_title']);
        }

        // 3. Gunakan jabatan eksplisit dari metadata atau pengalaman kerja.
        if (! $currentJob && ! empty($item['past_job_title'])) {
            $currentJob = trim($item['past_job_title']);
        }

        // 4. Fallback terakhir memakai pengalaman kerja pertama yang memiliki jabatan.
        if (! $currentJob && $firstJob) {
            $currentJob = $firstJob;
        }
        if (! $currentCompany && $firstCompany) {
            $currentCompany = $firstCompany;
            $currentCompanyLocation = $firstLoc;
            $currentCompanyUrl = $firstUrl;
        }

        // 8. Pendidikan (Education / Educations)
        $rawEdu = $item['education'] ?? $item['educations'] ?? $item['schools'] ?? [];
        $educationList = [];
        if (is_array($rawEdu)) {
            foreach ($rawEdu as $edu) {
                if (! is_array($edu)) {
                    continue;
                }
                $educationList[] = [
                    'school' => $edu['school_name'] ?? $edu['schoolName'] ?? $edu['school'] ?? $edu['name'] ?? null,
                    'degree' => $edu['degree_name'] ?? $edu['degree'] ?? $edu['degreeName'] ?? null,
                    'field_of_study' => $edu['field_of_study'] ?? $edu['fieldOfStudy'] ?? $edu['field'] ?? null,
                    'date' => $edu['date'] ?? null,
                    'school_url' => $edu['li_school_url'] ?? $edu['schoolUrl'] ?? null,
                    'start_date' => $edu['startDate'] ?? $edu['startYear'] ?? null,
                    'end_date' => $edu['endDate'] ?? $edu['endYear'] ?? null,
                ];
            }
        }

        // 9. Keahlian (Skills) - Mendukung berbagai skema key dan ekstraksi otomatis dari summary/about
        $rawSkills = $item['skills']
            ?? $item['skill']
            ?? $item['all_skills']
            ?? $item['skills_list']
            ?? $item['featured_skills']
            ?? $item['skill_list']
            ?? [];

        if (is_string($rawSkills) && ! empty($rawSkills)) {
            $rawSkills = array_map('trim', explode(',', $rawSkills));
        }

        $skillsList = [];
        if (is_array($rawSkills)) {
            foreach ($rawSkills as $skill) {
                if (is_string($skill) && ! empty(trim($skill))) {
                    $skillsList[] = trim($skill);
                } elseif (is_array($skill)) {
                    $skillName = $skill['name'] ?? $skill['title'] ?? $skill['skill'] ?? $skill['text'] ?? $skill['label'] ?? null;
                    if (! empty($skillName)) {
                        $skillsList[] = trim((string) $skillName);
                    }
                }
            }
        }

        // 10. Pengalaman Sukarelawan (Volunteer Experiences) jika tersedia
        $rawVol = $item['volunteer_experiences'] ?? $item['volunteering'] ?? [];
        $volunteerList = [];
        if (is_array($rawVol)) {
            foreach ($rawVol as $vol) {
                if (! is_array($vol)) {
                    continue;
                }
                $volunteerList[] = [
                    'role' => $vol['role'] ?? $vol['title'] ?? $vol['position'] ?? null,
                    'organization' => $vol['organization'] ?? $vol['company_name'] ?? $vol['name'] ?? null,
                    'cause' => $vol['cause'] ?? null,
                    'date' => $vol['date'] ?? null,
                    'description' => $vol['description'] ?? null,
                ];
            }
        }

        return [
            'profile_url' => $profileUrl,
            'profile_picture' => $profilePicture,
            'name' => $name,
            'first_name' => $item['first_name'] ?? $item['firstName'] ?? null,
            'last_name' => $item['last_name'] ?? $item['lastName'] ?? null,
            'headline' => $headline,
            'about' => $about,
            'location' => $location,
            'country' => $item['li_profile_country'] ?? null,
            'experience' => $experienceList,
            'experiences' => $experienceList,
            'education' => $educationList,
            'skills' => $skillsList,
            'volunteer_experiences' => $volunteerList,
            'past_job_title' => $item['past_job_title'] ?? null,
            'past_company_name' => $item['past_company_name'] ?? null,
            'past_company_url' => $item['past_li_company_url'] ?? null,
            'current_job' => $currentJob,
            'current_company' => $currentCompany,
            'current_company_location' => $currentCompanyLocation,
            'current_company_url' => $currentCompanyUrl,
            'handle' => $item['li_profile_handle'] ?? null,
            'followers' => isset($item['li_number_followers']) ? (int) $item['li_number_followers'] : null,
            'connections' => isset($item['li_number_connections']) ? (int) $item['li_number_connections'] : null,
            'scraped_at' => $item['scraped_at'] ?? null,
            'operation' => $item['operation'] ?? null,
        ];
    }
}
