<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'file_ids' => 'array',
        'file_s3_paths' => 'array',
        'migrated_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Rows still awaiting migration.
     */
    public function scopeUnmigrated(Builder $query): Builder
    {
        return $query->whereNull('migrated_at');
    }
}
