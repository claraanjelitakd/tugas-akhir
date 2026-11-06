<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'reviewed_at' => 'datetime',
        'scraped_at' => 'datetime',
    ];

    protected $appends = [
        'preview',
    ];

    /**
     * Accessor & Mutator agar scraped_data yang dikembalikan ke frontend selalu
     * tersusun rapi mengikuti urutan natural scraper alih-alih alfabetis biner MySQL JSON.
     */
    protected function scrapedData(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (is_null($value) || $value === '') {
                    return [];
                }
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (! is_array($decoded)) {
                    return [];
                }

                return self::orderPayloadKeys($decoded);
            },
            set: function ($value) {
                return is_string($value) ? $value : json_encode($value);
            }
        );
    }

    /**
     * Menyusun kembali kunci objek scraped_data mengikuti urutan natural scraper
     * alih-alih alfabetis murni dari serialisasi biner MySQL JSON.
     */
    public static function orderPayloadKeys(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        if (array_is_list($data)) {
            return array_map([self::class, 'orderPayloadKeys'], $data);
        }

        $priorityKeys = [
            'id', 'publicIdentifier', 'linkedinUrl', 'firstName', 'lastName', 'pronoun', 'emails',
            'headline', 'openToWork', 'hiring', 'premium', 'influencer', 'memorialized', 'creator',
            'location', 'objectUrn', 'registeredAt', 'topSkills', 'connectionsCount', 'followerCount',
            'verified', 'about', 'industry', 'currentPosition', 'profileTopEducation', 'profileActions',
            'profilePicture', 'coverPicture', 'photo', 'profileLocales', 'primaryLocale', 'multiLocaleHeadline',
            'services', 'experience', 'education', 'certifications', 'projects', 'volunteering',
            'receivedRecommendations', 'skills', 'publications', 'courses', 'patents', 'honorsAndAwards',
            'languages', 'organizations', 'causes', 'featured', 'composeOptionType', 'sectionTotals',
            'openToWorkCard', 'originalQuery',
            // Fallback legacy Apify / people.enrich
            'entity_type', 'operation', 'input', 'source_url', 'scraped_at', 'li_profile_id', 'li_profile_handle',
            'li_profile_url', 'full_name', 'first_name', 'last_name', 'li_profile_country',
            'li_profile_image_url', 'li_number_followers', 'li_number_connections', 'job_title', 'company_name',
            'li_company_url', 'past_job_title', 'past_company_name', 'past_li_company_url', 'school_name',
            'li_school_url', 'experiences',
        ];

        $subPriority = [
            'location' => ['linkedinText', 'countryCode', 'parsed'],
            'parsed' => ['text', 'countryCode', 'regionCode', 'country', 'countryFull', 'state', 'city'],
        ];

        $ordered = [];
        foreach ($priorityKeys as $key) {
            if (array_key_exists($key, $data)) {
                $val = $data[$key];
                if (is_array($val) && isset($subPriority[$key]) && ! array_is_list($val)) {
                    $orderedSub = [];
                    foreach ($subPriority[$key] as $sk) {
                        if (array_key_exists($sk, $val)) {
                            $orderedSub[$sk] = self::orderPayloadKeys($val[$sk]);
                            unset($val[$sk]);
                        }
                    }
                    foreach ($val as $sk => $sv) {
                        $orderedSub[$sk] = self::orderPayloadKeys($sv);
                    }
                    $ordered[$key] = $orderedSub;
                } else {
                    $ordered[$key] = self::orderPayloadKeys($val);
                }
                unset($data[$key]);
            }
        }

        // Sub-key khusus seperti parsed di dalam location
        if (isset($ordered['location']['parsed']) && is_array($ordered['location']['parsed']) && ! array_is_list($ordered['location']['parsed'])) {
            $parsedVal = $ordered['location']['parsed'];
            $orderedParsed = [];
            foreach ($subPriority['parsed'] as $pk) {
                if (array_key_exists($pk, $parsedVal)) {
                    $orderedParsed[$pk] = $parsedVal[$pk];
                    unset($parsedVal[$pk]);
                }
            }
            foreach ($parsedVal as $pk => $pv) {
                $orderedParsed[$pk] = $pv;
            }
            $ordered['location']['parsed'] = $orderedParsed;
        }

        foreach ($data as $key => $val) {
            $ordered[$key] = self::orderPayloadKeys($val);
        }

        return $ordered;
    }

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
     * Mendukung format HarvestAPI (harvestapi~linkedin-profile-scraper),
     * Apify data_forge_org~linkedin-scraper, serta skema standar profil LinkedIn.
     *
     * @return array{
     *     profile_url: ?string,
     *     name: ?string,
     *     first_name: ?string,
     *     last_name: ?string,
     *     headline: ?string,
     *     about: ?string,
     *     location: ?string,
     *     location_parsed: ?array,
     *     country: ?string,
     *     profile_picture: ?string,
     *     experience: array,
     *     experiences: array,
     *     education: array,
     *     skills: array,
     *     top_skills: array,
     *     all_skills: array,
     *     volunteer_experiences: array,
     *     volunteering: array,
     *     certifications: array,
     *     past_job_title: ?string,
     *     past_company_name: ?string,
     *     past_company_url: ?string,
     *     current_job: ?string,
     *     current_company: ?string,
     *     current_company_location: ?string,
     *     current_company_url: ?string,
     *     current_divisi: ?string,
     *     industry: ?string,
     *     bidang_pekerjaan: ?string,
     *     suggested_bidang_pekerjaan: ?string,
     *     further_study: ?array{
     *         has_study: bool,
     *         pendidikan_tingkat: ?string,
     *         degree_raw: ?string,
     *         perguruan_tinggi: ?string,
     *         pendidikan_prodi: ?string,
     *         date: ?string
     *     },
     *     further_studies: array<int, array<string, mixed>>,
     *     handle: ?string,
     *     followers: ?int,
     *     connections: ?int,
     *     scraped_at: ?string,
     *     operation: ?string
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
            ?? $item['photo']
            ?? $item['profile_picture']
            ?? $item['picture_url']
            ?? null;
        if (is_array($profilePicture)) {
            $profilePicture = $profilePicture['url']
                ?? ($profilePicture['sizes'][0]['url'] ?? null);
        }

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
            $location = is_array($item['location'])
                ? ($item['location']['parsed']['text']
                    ?? $item['location']['linkedinText']
                    ?? $item['location']['name']
                    ?? null)
                : $item['location'];
        } elseif (! empty($item['locationName'])) {
            $location = $item['locationName'];
        } elseif (! empty($item['geoCountryName'])) {
            $location = $item['geoCountryName'];
        }

        // 7. Pengalaman Kerja (Experience / Experiences)
        $rawExp = $this->asList($item['currentPosition'] ?? []);
        $rawExp = array_merge($rawExp, $this->asList($item['experience'] ?? $item['experiences'] ?? $item['positions'] ?? $item['position'] ?? []));

        // Deduplikasi entri posisi pekerjaan berdasarkan judul dan nama perusahaan
        $deduplicatedExp = [];
        $seenPositions = [];
        foreach ($rawExp as $pos) {
            if (! is_array($pos)) {
                continue;
            }
            $t = strtolower((string) ($this->textValue($this->firstValue($pos, ['position', 'title', 'jobTitle', 'role'])) ?? ''));
            $c = strtolower((string) ($this->textValue($this->firstValue($pos, ['companyName', 'company_name', 'company', 'name'])) ?? ''));
            $dedupKey = "{$t}|{$c}";
            if ($dedupKey !== '|' && isset($seenPositions[$dedupKey])) {
                continue;
            }
            $seenPositions[$dedupKey] = true;
            $deduplicatedExp[] = $pos;
        }

        $experienceList = [];
        $currentJob = null;
        $currentCompany = null;
        $currentCompanyLocation = null;
        $currentCompanyUrl = null;
        $currentDivision = null;

        $activeJob = null;
        $activeCompany = null;
        $activeLoc = null;
        $activeUrl = null;

        $firstJob = null;
        $firstCompany = null;
        $firstLoc = null;
        $firstUrl = null;
        $firstDivision = null;

        if (! empty($deduplicatedExp)) {
            foreach ($deduplicatedExp as $pos) {
                if (! is_array($pos)) {
                    continue;
                }

                $jobTitle = $this->textValue($this->firstValue($pos, ['position', 'title', 'jobTitle', 'role']));
                $company = $this->textValue($this->firstValue($pos, ['companyName', 'company_name', 'company', 'name']));
                $division = $this->textValue($this->firstValue($pos, [
                    'division', 'department', 'departmentName', 'unit', 'team',
                ]));

                $loc = null;
                if (! empty($pos['location'])) {
                    $loc = is_array($pos['location'])
                        ? ($pos['location']['parsed']['text'] ?? $pos['location']['linkedinText'] ?? null)
                        : $this->textValue($pos['location']);
                } elseif (! empty($this->firstValue($pos, ['locationName']))) {
                    $loc = $this->textValue($this->firstValue($pos, ['locationName']));
                }

                $compUrl = $this->textValue($this->firstValue($pos, ['companyLinkedinUrl', 'li_company_url', 'company_url', 'companyUrl']));
                $dateStr = $pos['date'] ?? ($pos['duration'] ?? '');
                $endDate = $pos['endDate'] ?? null;
                $endDateText = is_array($endDate) ? ($endDate['text'] ?? null) : $endDate;

                $isCurrent = (isset($pos['isCurrent']) && $pos['isCurrent'] === true)
                    || (is_string($dateStr) && (str_contains(strtolower($dateStr), 'present') || str_contains(strtolower($dateStr), 'saat ini')))
                    || (array_key_exists('endDate', $pos) && (is_null($endDateText) || strcasecmp(trim((string) $endDateText), 'Present') === 0 || strcasecmp(trim((string) $endDateText), 'Saat ini') === 0));

                $companyLogo = null;
                if (! empty($pos['companyLogo'])) {
                    $companyLogo = is_array($pos['companyLogo'])
                        ? ($pos['companyLogo']['url'] ?? ($pos['companyLogo']['sizes'][0]['url'] ?? null))
                        : $pos['companyLogo'];
                } elseif (! empty($pos['company_logo_url'])) {
                    $companyLogo = $pos['company_logo_url'];
                }

                $experienceList[] = [
                    'position' => $jobTitle,
                    'company' => $company,
                    'division' => $division,
                    'location' => $loc,
                    'date' => ! empty($dateStr) ? $dateStr : null,
                    'time_period' => $pos['duration'] ?? $pos['job_time_period'] ?? null,
                    'company_url' => $compUrl,
                    'company_logo' => $companyLogo,
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
                    $currentDivision = $division;
                }

                if (! $firstJob && $jobTitle) {
                    $firstJob = $jobTitle;
                    $firstCompany = $company;
                    $firstLoc = $loc;
                    $firstUrl = $compUrl;
                    $firstDivision = $division;
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
        if (! $currentCompany) {
            $currentCompany = $this->textValue($this->firstValue($item, [
                'current_company', 'currentCompany', 'company_name', 'companyName', 'company',
            ]));
            $currentCompanyLocation = $this->textValue($this->firstValue($item, [
                'current_company_location', 'currentCompanyLocation', 'company_location',
            ]));
            $currentCompanyUrl = $this->textValue($this->firstValue($item, [
                'current_company_url', 'currentCompanyUrl', 'li_company_url', 'company_url', 'companyUrl',
            ]));
            $currentDivision = $this->textValue($this->firstValue($item, [
                'current_division', 'currentDivision', 'division', 'department', 'departmentName', 'unit', 'team',
            ]));
        }
        if (! $currentDivision) {
            $currentDivision = $this->textValue($this->firstValue($item, [
                'current_division', 'currentDivision', 'division', 'department', 'departmentName', 'unit', 'team',
            ]));
        }
        if (! $currentJob) {
            $currentJob = $this->textValue($this->firstValue($item, [
                'current_job', 'currentJob', 'job_title', 'jobTitle', 'title', 'position',
            ]));
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
            $currentDivision = $firstDivision;
        }

        // 8. Pendidikan (Education / Educations)
        $rawEdu = $item['education'] ?? $item['educations'] ?? $item['schools'] ?? $item['profileTopEducation'] ?? [];
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

        // 9. Keahlian (Skills):
        // Prioritas utama menggunakan 'topSkills' sesuai permintaan pengguna ("yang skills pakai top skills")
        $rawTopSkills = $item['topSkills'] ?? [];
        $rawAllSkills = $item['skills']
            ?? $item['skill']
            ?? $item['all_skills']
            ?? $item['skills_list']
            ?? $item['featured_skills']
            ?? $item['skill_list']
            ?? [];

        $topSkillsList = [];
        if (is_array($rawTopSkills)) {
            foreach ($rawTopSkills as $s) {
                if (is_string($s) && trim($s) !== '') {
                    $topSkillsList[] = trim($s);
                } elseif (is_array($s) && ! empty($s['name'])) {
                    $topSkillsList[] = trim((string) $s['name']);
                }
            }
        } elseif (is_string($rawTopSkills) && trim($rawTopSkills) !== '') {
            $parts = preg_split('/[,•|]/u', $rawTopSkills) ?: [];
            $topSkillsList = array_values(array_filter(array_map('trim', $parts)));
        }

        $allSkillsList = [];
        if (is_array($rawAllSkills)) {
            foreach ($rawAllSkills as $skill) {
                if (is_string($skill) && trim($skill) !== '') {
                    $allSkillsList[] = trim($skill);
                } elseif (is_array($skill)) {
                    $skillName = $skill['name'] ?? $skill['title'] ?? $skill['skill'] ?? $skill['text'] ?? $skill['label'] ?? null;
                    if (! empty($skillName)) {
                        $allSkillsList[] = trim((string) $skillName);
                    }
                }
            }
        } elseif (is_string($rawAllSkills) && trim($rawAllSkills) !== '') {
            $parts = preg_split('/[,•|]/u', $rawAllSkills) ?: [];
            $allSkillsList = array_values(array_filter(array_map('trim', $parts)));
        }

        // Skills aktif: wajib utamakan topSkills jika tersedia, fallback ke allSkills
        $skillsList = ! empty($topSkillsList) ? $topSkillsList : $allSkillsList;

        // 10. Pengalaman Sukarelawan & Organisasi (Volunteering) jika tersedia
        $rawVol = $item['volunteering'] ?? $item['volunteer_experiences'] ?? [];
        $volunteerList = [];
        if (is_array($rawVol)) {
            foreach ($rawVol as $vol) {
                if (! is_array($vol)) {
                    continue;
                }
                $volunteerList[] = [
                    'role' => $vol['role'] ?? $vol['title'] ?? $vol['position'] ?? null,
                    'organization' => $vol['organizationName'] ?? $vol['organization'] ?? $vol['company_name'] ?? $vol['name'] ?? null,
                    'cause' => $vol['cause'] ?? null,
                    'duration' => $vol['duration'] ?? $vol['date'] ?? null,
                    'description' => $vol['description'] ?? null,
                    'start_date' => $vol['startDate'] ?? null,
                    'end_date' => $vol['endDate'] ?? null,
                ];
            }
        }

        // 11. Sertifikasi & Lisensi (Certifications) jika tersedia
        $rawCert = $item['certifications'] ?? [];
        $certificationList = [];
        if (is_array($rawCert)) {
            foreach ($rawCert as $cert) {
                if (! is_array($cert)) {
                    continue;
                }
                $certLogo = null;
                if (! empty($cert['issuedByLogo'])) {
                    $certLogo = is_array($cert['issuedByLogo'])
                        ? ($cert['issuedByLogo']['url'] ?? ($cert['issuedByLogo']['sizes'][0]['url'] ?? null))
                        : $cert['issuedByLogo'];
                }
                $certificationList[] = [
                    'title' => $cert['title'] ?? null,
                    'issued_by' => $cert['issuedBy'] ?? $cert['authority'] ?? null,
                    'issued_at' => $cert['issuedAt'] ?? null,
                    'credential_id' => $cert['credentialId'] ?? null,
                    'link' => $cert['link'] ?? $cert['issuedByLink'] ?? null,
                    'logo' => $certLogo,
                ];
            }
        }

        // 12. Bidang Industri / Divisi Pekerjaan
        $industry = null;
        if (! empty($item['industry'])) {
            $industry = is_array($item['industry']) ? ($item['industry']['name'] ?? null) : $item['industry'];
        } elseif (! empty($item['company_industry'])) {
            $industry = $item['company_industry'];
        }

        // 13. Deteksi Riwayat Studi Lanjut (Further Study Detection)
        $furtherStudies = [];
        $primaryFurtherStudy = null;
        $highestScore = -1;

        foreach ($educationList as $edu) {
            $school = trim((string) ($edu['school'] ?? ''));
            $degree = trim((string) ($edu['degree'] ?? ''));
            $field = trim((string) ($edu['field_of_study'] ?? ''));
            $date = trim((string) ($edu['date'] ?? ''));
            $endDate = is_array($edu['end_date']) ? ($edu['end_date']['text'] ?? '') : (string) ($edu['end_date'] ?? '');

            if ($school === '' && $degree === '' && $field === '') {
                continue;
            }

            $isUkdw = str_contains(strtolower($school), 'duta wacana') || str_contains(strtolower($school), 'ukdw');
            $isDoctor = (bool) preg_match('/(doktor|doctor|ph\.?d|s3)/i', $degree);
            $isMaster = (bool) preg_match('/(master|magister|s2|mba|m\.sc|m\.kom|m\.ti|m\.si)/i', $degree);
            $isProfesi = (bool) preg_match('/(profesi|spesialis)/i', $degree);
            $isBachelor = (bool) preg_match('/(bachelor|sarjana|s1)/i', $degree);
            $isActive = str_contains(strtolower($date), 'present') || str_contains(strtolower($endDate), 'present') || str_contains(strtolower($date), 'saat ini');

            $score = 0;
            if ($isDoctor) {
                $score += 100;
            } elseif ($isMaster) {
                $score += 80;
            } elseif ($isProfesi) {
                $score += 70;
            } elseif (! $isUkdw && ! $isBachelor) {
                $score += 50;
            } elseif (! $isUkdw) {
                $score += 30;
            }

            if ($isActive) {
                $score += 20;
            }

            $normalizedTingkat = $degree;
            if ($isDoctor) {
                $normalizedTingkat = 'S3';
            } elseif ($isMaster) {
                $normalizedTingkat = 'S2';
            } elseif ($isProfesi) {
                $normalizedTingkat = 'Profesi';
            }

            $studyItem = [
                'school' => $school,
                'degree' => $degree,
                'degree_normalized' => $normalizedTingkat,
                'field_of_study' => $field,
                'date' => $date,
                'is_active' => $isActive,
                'is_almamater' => $isUkdw,
                'score' => $score,
            ];

            if (! ($isUkdw && $isBachelor)) {
                $furtherStudies[] = $studyItem;
                if ($score > $highestScore) {
                    $highestScore = $score;
                    $primaryFurtherStudy = $studyItem;
                }
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
            'location_parsed' => is_array($item['location']['parsed'] ?? null) ? $item['location']['parsed'] : null,
            'country' => $item['li_profile_country'] ?? ($item['location']['countryCode'] ?? null),
            'experience' => $experienceList,
            'experiences' => $experienceList,
            'education' => $educationList,
            'skills' => $skillsList,
            'top_skills' => $topSkillsList,
            'all_skills' => $allSkillsList,
            'volunteer_experiences' => $volunteerList,
            'volunteering' => $volunteerList,
            'certifications' => $certificationList,
            'past_job_title' => $item['past_job_title'] ?? null,
            'past_company_name' => $item['past_company_name'] ?? null,
            'past_company_url' => $item['past_li_company_url'] ?? null,
            'current_job' => $currentJob,
            'current_company' => $currentCompany,
            'current_company_location' => $currentCompanyLocation,
            'current_company_url' => $currentCompanyUrl,
            'current_divisi' => $currentDivision,
            'industry' => $industry,
            'bidang_pekerjaan' => $industry,
            'suggested_bidang_pekerjaan' => $industry,
            'further_study' => $primaryFurtherStudy ? [
                'has_study' => true,
                'pendidikan_tingkat' => $primaryFurtherStudy['degree_normalized'],
                'degree_raw' => $primaryFurtherStudy['degree'],
                'perguruan_tinggi' => $primaryFurtherStudy['school'],
                'pendidikan_prodi' => $primaryFurtherStudy['field_of_study'],
                'date' => $primaryFurtherStudy['date'],
            ] : null,
            'further_studies' => $furtherStudies,
            'handle' => $item['li_profile_handle'] ?? $item['publicIdentifier'] ?? null,
            'followers' => isset($item['li_number_followers'])
                ? (int) $item['li_number_followers']
                : (isset($item['followerCount']) ? (int) $item['followerCount'] : null),
            'connections' => isset($item['li_number_connections'])
                ? (int) $item['li_number_connections']
                : (isset($item['connectionsCount']) ? (int) $item['connectionsCount'] : null),
            'scraped_at' => $item['scraped_at'] ?? null,
            'operation' => $item['operation'] ?? null,
        ];
    }

    /**
     * Mengubah nilai actor yang dapat berupa satu objek atau daftar objek menjadi daftar seragam.
     *
     * @param  mixed  $value  Nilai mentah dari respons actor.
     * @return list<array<string, mixed>>
     */
    private function asList(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    /**
     * Mengambil teks dari field provider yang dapat berupa string atau objek bersarang.
     */
    private function textValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $this->firstValue($value, ['name', 'localized', 'text', 'value']);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    /**
     * Mengambil field pertama yang benar-benar ada tanpa memicu notice pada payload provider lama.
     *
     * @param  array<string, mixed>  $data  Payload yang sedang dibaca.
     * @param  array<int, string>  $keys  Urutan alias field yang diprioritaskan.
     */
    private function firstValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }
}
