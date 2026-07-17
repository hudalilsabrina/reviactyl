<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property string $pattern
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Server $server
 */
class ServerConfigWatchPattern extends Model
{
    protected $table = 'server_config_watch_patterns';

    protected $fillable = ['server_id', 'pattern'];

    protected $casts = [
        'server_id' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Default watch patterns used when no custom patterns exist.
     */
    public static function defaults(): array
    {
        return [
            '*.properties',
            '*.yml',
            '*.yaml',
            '*.json',
            '*.toml',
            '*.cfg',
            '*.conf',
            '*.ini',
        ];
    }
}
