<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TransactionType extends Model
{
    use HasActiveFlag, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'is_active'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
