<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $server_id
 * @property int $author_id
 * @property int|null $parent_id
 * @property string $message
 * @property string $hash
 * @property bool $is_preset
 * @property string|null $preset_name
 * @property int $file_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Server $server
 * @property User $author
 * @property ServerConfigRevision|null $parent
 * @property Collection|ServerConfigFile[] $files
 */
class ServerConfigRevision extends Model
{
    public const RESOURCE_NAME = 'config_revision';

    protected $table = 'server_config_revisions';

    protected $fillable = [
        'server_id',
        'author_id',
        'parent_id',
        'message',
        'hash',
        'is_preset',
        'preset_name',
        'file_count',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'author_id' => 'integer',
        'parent_id' => 'integer',
        'is_preset' => 'boolean',
        'file_count' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ServerConfigFile::class, 'revision_id');
    }
}
