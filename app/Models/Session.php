<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Database-driver session rows; used to sign a user out everywhere. */
class Session extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
