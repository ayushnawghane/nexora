<?php

namespace App\Models;

use App\Enums\JobSheetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A deal's progress on one job sheet activity. Every submit and check is in the activity log.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $job_sheet_activity_id
 * @property JobSheetStatus $status
 * @property Carbon $received_on
 * @property int $maker_id
 * @property string|null $maker_comment
 * @property Carbon $made_at
 * @property int|null $checker_id
 * @property string|null $checker_comment
 * @property Carbon|null $checked_at
 */
class DealJobSheetEntry extends Model
{
    use LogsActivity;

    protected $fillable = [
        'job_sheet_activity_id', 'status', 'received_on', 'maker_id', 'maker_comment', 'made_at',
        'checker_id', 'checker_comment', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobSheetStatus::class,
            'received_on' => 'date',
            'made_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<JobSheetActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(JobSheetActivity::class, 'job_sheet_activity_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
