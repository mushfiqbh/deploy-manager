<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'user_id',
        'kind',
        'status',
        'version_before',
        'version_after',
        'command',
        'output',
        'exit_code',
        'started_at',
        'finished_at',
    ];

    public const KIND_FIRST  = 'first';   // ran deploy.sh
    public const KIND_UPDATE = 'update';  // ran up.sh

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR   = 'error';

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
