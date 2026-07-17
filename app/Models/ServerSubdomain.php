<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSubdomain extends Model
{
    public const RESOURCE_NAME = 'server_subdomain';

    protected $table = 'server_subdomains';

    protected $fillable = [
        'server_id',
        'subdomain',
        'domain',
        'record_id',
        'ip_address',
        'is_auto_generated',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'is_auto_generated' => 'boolean',
    ];

    public static array $validationRules = [
        'subdomain' => 'required|string|max:63|regex:/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/',
        'domain' => 'required|string|max:255',
        'ip_address' => 'required|string|max:45',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Full FQDN: subdomain.domain
     */
    public function getFqdnAttribute(): string
    {
        return $this->subdomain.'.'.$this->domain;
    }
}
