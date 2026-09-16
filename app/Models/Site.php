<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'path',
        'branch',
        'mode',
        'first_deployed',
        'state',
        'current_version',
        'last_deployed_at',
    ];

    protected $casts = [
        'last_deployed_at' => 'datetime',
        'first_deployed'   => 'boolean',
    ];

    public const STATE_PENDING = 'pending';
    public const STATE_RUNNING = 'running';
    public const STATE_OK      = 'ok';
    public const STATE_ERROR   = 'error';

    public const MODE_NEW    = 'new';
    public const MODE_UPDATE = 'update';

    public function logs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class)->latest('id');
    }

    public function latestLog()
    {
        return $this->hasOne(DeploymentLog::class)->latestOfMany('id');
    }
}
