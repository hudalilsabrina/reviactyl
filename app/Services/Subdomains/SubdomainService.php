<?php

namespace App\Services\Subdomains;

use App\Contracts\Repository\SettingsRepositoryInterface;
use App\Exceptions\DisplayException;
use App\Models\Server;
use App\Models\ServerSubdomain;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubdomainService
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
    ) {}

    /**
     * Check if the subdomain feature is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return $this->settings->get('settings::subdomains:enabled', false)
            && ! empty($this->getBaseDomains())
            && filled($this->getApiToken());
    }

    /**
     * Get base domains as an array.
     *
     * @return string[]
     */
    public function getBaseDomains(): array
    {
        $raw = $this->settings->get('settings::subdomains:base_domains', '');

        if (empty($raw)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $raw)));
    }

    /**
     * Get zone IDs as an array.
     *
     * @return string[]
     */
    public function getZoneIds(): array
    {
        $raw = $this->settings->get('settings::subdomains:cloudflare_zone_ids', '');

        if (empty($raw)) {
            return [];
        }

        // Handle JSON array format
        if (str_starts_with($raw, '[')) {
            return array_filter(json_decode($raw, true) ?? []);
        }

        return array_filter(array_map('trim', explode(',', $raw)));
    }

    /**
     * Get the zone ID for a specific base domain.
     * Zone IDs and base domains are stored as parallel arrays.
     */
    public function getZoneIdForDomain(string $domain): ?string
    {
        $domains = $this->getBaseDomains();
        $zoneIds = $this->getZoneIds();

        $index = array_search($domain, $domains, true);

        if ($index === false || ! isset($zoneIds[$index])) {
            // Fallback to first zone ID
            return $zoneIds[0] ?? null;
        }

        return $zoneIds[$index];
    }

    /**
     * Get the Cloudflare DNS service instance for a specific zone.
     */
    public function getCloudflareService(string $zoneId): CloudflareDnsService
    {
        return new CloudflareDnsService(
            apiToken: $this->getApiToken(),
            zoneId: $zoneId,
        );
    }

    /**
     * Auto-create a subdomain for a server based on its name.
     */
    public function createAutoSubdomain(Server $server): ?ServerSubdomain
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $ipAddress = $this->resolveAllocationIp($server);
        if (! $ipAddress) {
            Log::warning('Subdomain auto-create skipped: no resolvable IP', ['server_id' => $server->id]);

            return null;
        }

        // Use the first configured domain for auto-generated subdomains
        $baseDomain = $this->getBaseDomains()[0];
        $subdomain = $this->generateUniqueSlug($server->name, $server->id, $baseDomain);
        $fullDomain = $subdomain.'.'.$baseDomain;

        $zoneId = $this->getZoneIdForDomain($baseDomain);
        $cloudflare = $this->getCloudflareService($zoneId);
        $recordId = $cloudflare->createARecord($fullDomain, $ipAddress);

        if (! $recordId) {
            Log::error('Subdomain auto-create failed: Cloudflare DNS error', [
                'server_id' => $server->id,
                'domain' => $fullDomain,
            ]);

            return null;
        }

        try {
            return ServerSubdomain::create([
                'server_id' => $server->id,
                'subdomain' => $subdomain,
                'domain' => $baseDomain,
                'record_id' => $recordId,
                'ip_address' => $ipAddress,
                'is_auto_generated' => true,
            ]);
        } catch (QueryException $e) {
            Log::warning('Subdomain auto-create race condition, cleaning up Cloudflare record', [
                'server_id' => $server->id,
                'domain' => $fullDomain,
            ]);
            $cloudflare->deleteRecord($recordId);

            return null;
        }
    }

    /**
     * Create a custom subdomain for a server.
     *
     * @throws DisplayException
     */
    public function createCustomSubdomain(Server $server, string $subdomain, ?string $domain = null): ServerSubdomain
    {
        if (! $this->isEnabled()) {
            throw new DisplayException('Subdomain management is not enabled.');
        }

        $maxPerServer = $this->getMaxPerServer();
        if ($maxPerServer > 0) {
            $currentCount = ServerSubdomain::where('server_id', $server->id)
                ->where('is_auto_generated', false)
                ->count();

            if ($currentCount >= $maxPerServer) {
                throw new DisplayException(
                    "You have reached the maximum number of custom subdomains ({$maxPerServer}) for this server."
                );
            }
        }

        // Validate domain is one of the configured base domains
        $baseDomains = $this->getBaseDomains();
        $domain = $domain ?? $baseDomains[0];

        if (! in_array($domain, $baseDomains, true)) {
            throw new DisplayException("The domain '{$domain}' is not configured for subdomains.");
        }

        $fullDomain = $subdomain.'.'.$domain;

        // Check if this subdomain is already used
        $existing = ServerSubdomain::where('subdomain', $subdomain)
            ->where('domain', $domain)
            ->first();

        if ($existing) {
            throw new DisplayException("The subdomain '{$fullDomain}' is already in use.");
        }

        // Check Cloudflare too
        $zoneId = $this->getZoneIdForDomain($domain);
        $cloudflare = $this->getCloudflareService($zoneId);

        if ($cloudflare->recordExists($fullDomain)) {
            throw new DisplayException("A DNS record for '{$fullDomain}' already exists in Cloudflare.");
        }

        $ipAddress = $this->resolveAllocationIp($server);
        if (! $ipAddress) {
            throw new DisplayException('Could not determine an IP address for this server.');
        }

        $recordId = $cloudflare->createARecord($fullDomain, $ipAddress);

        if (! $recordId) {
            throw new DisplayException('Failed to create DNS record in Cloudflare.');
        }

        try {
            return ServerSubdomain::create([
                'server_id' => $server->id,
                'subdomain' => $subdomain,
                'domain' => $domain,
                'record_id' => $recordId,
                'ip_address' => $ipAddress,
                'is_auto_generated' => false,
            ]);
        } catch (QueryException $e) {
            $cloudflare->deleteRecord($recordId);
            throw new DisplayException("The subdomain '{$fullDomain}' is already in use.");
        }
    }

    /**
     * Update a subdomain's prefix.
     * Creates the new DNS record first, then deletes the old one to avoid downtime.
     *
     * @throws DisplayException
     */
    public function updateSubdomain(ServerSubdomain $subdomain, string $newSubdomain): ServerSubdomain
    {
        if (! $this->isEnabled()) {
            throw new DisplayException('Subdomain management is not enabled.');
        }

        $fullDomain = $newSubdomain.'.'.$subdomain->domain;

        // Check uniqueness in DB
        $existing = ServerSubdomain::where('subdomain', $newSubdomain)
            ->where('domain', $subdomain->domain)
            ->where('id', '!=', $subdomain->id)
            ->first();

        if ($existing) {
            throw new DisplayException("The subdomain '{$fullDomain}' is already in use.");
        }

        $zoneId = $this->getZoneIdForDomain($subdomain->domain);
        $cloudflare = $this->getCloudflareService($zoneId);

        // Create new record FIRST — if this fails, old record stays intact
        $newRecordId = $cloudflare->createARecord($fullDomain, $subdomain->ip_address);

        if (! $newRecordId) {
            throw new DisplayException('Failed to create new DNS record in Cloudflare. The old subdomain remains active.');
        }

        // Now delete old record (best-effort, don't fail if it's already gone)
        if ($subdomain->record_id) {
            $cloudflare->deleteRecord($subdomain->record_id);
        }

        $subdomain->update([
            'subdomain' => $newSubdomain,
            'record_id' => $newRecordId,
        ]);

        return $subdomain->refresh();
    }

    /**
     * Delete a subdomain and its Cloudflare DNS record.
     */
    public function deleteSubdomain(ServerSubdomain $subdomain): bool
    {
        if ($subdomain->record_id) {
            $zoneId = $this->getZoneIdForDomain($subdomain->domain);
            $cloudflare = $this->getCloudflareService($zoneId);
            $cloudflare->deleteRecord($subdomain->record_id);
        }

        return $subdomain->delete();
    }

    /**
     * Delete all subdomains for a server (used during server deletion).
     * Cloudflare cleanup is best-effort — DB rows are always deleted.
     */
    public function deleteAllForServer(Server $server): void
    {
        $subdomains = ServerSubdomain::where('server_id', $server->id)->get();

        if ($subdomains->isEmpty()) {
            return;
        }

        // Best-effort Cloudflare cleanup — don't let failures block server deletion
        if ($this->isEnabled()) {
            // Group subdomains by domain to minimize zone lookups
            $byDomain = $subdomains->groupBy('domain');

            foreach ($byDomain as $domain => $domainSubdomains) {
                try {
                    $zoneId = $this->getZoneIdForDomain($domain);
                    $cloudflare = $this->getCloudflareService($zoneId);

                    foreach ($domainSubdomains as $subdomain) {
                        if ($subdomain->record_id) {
                            try {
                                $cloudflare->deleteRecord($subdomain->record_id);
                            } catch (\Throwable $e) {
                                Log::warning('Failed to delete Cloudflare record during server cleanup', [
                                    'record_id' => $subdomain->record_id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to initialize Cloudflare service during server cleanup', [
                        'domain' => $domain,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Always delete DB rows regardless of Cloudflare success
        ServerSubdomain::where('server_id', $server->id)->delete();
    }

    /**
     * Resolve the IP address from a server's primary allocation.
     */
    private function resolveAllocationIp(Server $server): ?string
    {
        $allocation = $server->allocation;
        if (! $allocation) {
            return null;
        }

        $ip = $allocation->ip;

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        $resolved = @gethostbyname($ip);
        if (filter_var($resolved, FILTER_VALIDATE_IP) && $resolved !== $ip) {
            return $resolved;
        }

        return null;
    }

    /**
     * Generate a unique subdomain slug from a server name.
     */
    private function generateUniqueSlug(string $name, int $serverId, string $domain): string
    {
        $slug = Str::slug($name, '-');

        if (strlen($slug) < 2) {
            $slug = 'srv-'.$serverId;
        }

        $slug = substr($slug, 0, 63);
        $slug = rtrim($slug, '-');

        if (strlen($slug) < 2) {
            $slug = 'srv-'.$serverId;
        }

        $original = $slug;
        $counter = 1;
        $maxAttempts = 100;

        while (ServerSubdomain::where('subdomain', $slug)->where('domain', $domain)->exists()) {
            $suffix = '-'.$counter;
            $slug = substr($original, 0, 63 - strlen($suffix)).$suffix;
            $counter++;

            if ($counter > $maxAttempts) {
                $slug = 'srv-'.Str::random(8);
                break;
            }
        }

        return $slug;
    }

    private function getApiToken(): ?string
    {
        return $this->settings->get('settings::subdomains:cloudflare_api_token', null);
    }

    public function getMaxPerServer(): int
    {
        return (int) $this->settings->get('settings::subdomains:max_per_server', 1);
    }
}
