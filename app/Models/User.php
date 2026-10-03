<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $ulid
 * @property string $emp_code
 * @property string $name
 * @property string $email
 * @property string|null $mobile
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property int|null $reporting_manager_id
 * @property bool $is_authorised_signatory
 * @property string|null $signature_path
 * @property Carbon|null $date_of_joining
 * @property string $password
 * @property Carbon|null $password_changed_at
 * @property bool $must_change_password
 * @property string|null $two_factor_secret
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $theme
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int|null $legacy_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasActiveFlag, HasFactory, HasRoles, HasUlids, LogsActivity, Notifiable, SoftDeletes;

    public const PASSWORD_MAX_AGE_DAYS = 90;

    protected $fillable = [
        'emp_code',
        'name',
        'email',
        'mobile',
        'department_id',
        'designation_id',
        'reporting_manager_id',
        'is_authorised_signatory',
        'date_of_joining',
        'is_active',
        'theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'theme' => null,
        'is_active' => true,
        'must_change_password' => true,
        'is_authorised_signatory' => false,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'is_authorised_signatory' => 'boolean',
            'date_of_joining' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Only the public ulid column is generated; the numeric id stays the primary key.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_manager_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(User::class, 'reporting_manager_id');
    }

    /**
     * @return BelongsToMany<Vertical, $this>
     */
    public function verticals(): BelongsToMany
    {
        return $this->belongsToMany(Vertical::class);
    }

    /**
     * @return BelongsToMany<VerticalTeam, $this>
     */
    public function verticalTeams(): BelongsToMany
    {
        return $this->belongsToMany(VerticalTeam::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return HasMany<LoginEvent, $this>
     */
    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    /**
     * @return HasMany<Session, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function passwordNeedsChange(): bool
    {
        if ($this->must_change_password) {
            return true;
        }

        return $this->password_changed_at === null
            || $this->password_changed_at->lt(Carbon::now()->subDays(self::PASSWORD_MAX_AGE_DAYS));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([...$this->fillable, 'must_change_password', 'two_factor_confirmed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
