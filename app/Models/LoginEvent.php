<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    public const REASON_INVALID_CREDENTIALS = 'invalid_credentials';

    public const REASON_INACTIVE = 'inactive';

    public const REASON_THROTTLED = 'throttled';

    public const REASON_TWO_FACTOR_FAILED = 'two_factor_failed';

    protected $fillable = ['user_id', 'emp_code', 'successful', 'reason', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['successful' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
