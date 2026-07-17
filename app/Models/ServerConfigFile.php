<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $revision_id
 * @property string $file_path
 * @property string $content_hash
 * @property int $content_length
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ServerConfigRevision $revision
 */
class ServerConfigFile extends Model
{
    protected $table = 'server_config_files';

    protected $fillable = [
        'revision_id',
        'file_path',
        'content_hash',
        'content_length',
    ];

    protected $casts = [
        'revision_id' => 'integer',
        'content_length' => 'integer',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ServerConfigRevision::class, 'revision_id');
    }
}
