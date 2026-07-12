<?php

namespace App\Services\Subdomains;

use App\Contracts\Repository\SettingsRepositoryInterface;
use App\Exceptions\DisplayException;
use App\Models\Allocation;
use App\Models\Server;
use App\Models\ServerSubdomain;
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
            && filled($this->getBaseDomain())
            && filled($this->getApiToken())
            && filled($this->getZoneId());
    }

    /**
     * Get the Cloudflare DNS service instance.
     */
    public function getCloudflareService(): CloudflareDnsService
    {
        return new CloudflareDnsService(
            apiToken: $this->getApiToken(),
            zoneId: $this->getZoneId(),
        );
    }

    /**
     * Auto-create a subdomain for a server based on its name.
     *
     * @throws DisplayException
     */
    public function createAutoSubdomain(Server $server): ?ServerSubdomain
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $ipAddress = $this->resolveAllocationIp($server);
        if (! $ipAddress) {
            return null;
        }

        $baseDomain = $this->getBaseDomain();
        $subdomain = $this->generateUniqueSlug($server->name, $baseDomain);
        $fullDomain = $subdomain.'.'.$baseDomain;

        $cloudflare = $this->getCloudflareService();
        $recordId = $cloudflare->createARecord($fullDomain, $ipAddress);

        if (! $recordId) {
            return null;
        }

        return ServerSubdomain::create([
            'server_id' => $server->id,
            'subdomain' => $subdomain,
            'domain' => $baseDomain,
            'record_id' => $recordId,
            'ip_address' => $ipAddress,
            'is_auto_generated' => true,
        ]);
    }

    /**
     * Create a custom subdomain for a server.
     *
     * @throws DisplayException
     */
    public function createCustomSubdomain(Server $server, string $subdomain, ?string $customDomain = null): ServerSubdomain
    {
        if (! $this->isEnabled()) {
            throw new DisplayException('Subdomain management is not enabled.');
        }

        $domain = $customDomain ?? $this->getBaseDomain();
        $fullDomain = $subdomain.'.'.$domain;

        // Check if this subdomain is already used
        $existing = ServerSubdomain::where('subdomain', $subdomain)
            ->where('domain', $domain)
            ->first();

        if ($existing) {
            throw new DisplayException("The subdomain '{$fullDomain}' is already in use.");
        }

        // Check Cloudflare too
        $cloudflare = $this->getCloudflareService();
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

        return ServerSubdomain::create([
            'server_id' => $server->id,
            'subdomain' => $subdomain,
            'domain' => $domain,
            'record_id' => $recordId,
            'ip_address' => $ipAddress,
            'is_auto_generated' => false,
        ]);
    }

    /**
     * Update a subdomain's prefix.
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

        $cloudflare = $this->getCloudflareService();

        // Delete old record
        if ($subdomain->record_id) {
            $cloudflare->deleteRecord($subdomain->record_id);
        }

        // Create new record
        $recordId = $cloudflare->createARecord($fullDomain, $subdomain->ip_address);

        if (! $recordId) {
            throw new DisplayException('Failed to create DNS record in Cloudflare.');
        }

        $subdomain->update([
            'subdomain' => $newSubdomain,
            'record_id' => $recordId,
        ]);

        return $subdomain->refresh();
    }

    /**
     * Delete a subdomain and its Cloudflare DNS record.
     */
    public function deleteSubdomain(ServerSubdomain $subdomain): bool
    {
        if ($subdomain->record_id) {
            $cloudflare = $this->getCloudflareService();
            $cloudflare->deleteRecord($subdomain->record_id);
        }

        return $subdomain->delete();
    }

    /**
     * Delete all subdomains for a server (used during server deletion).
     */
    public function deleteAllForServer(Server $server): void
    {
        if (! $this->isEnabled()) {
            // Still clean up DB records even if Cloudflare is disabled
            ServerSubdomain::where('server_id', $server->id)->delete();

            return;
        }

        $cloudflare = $this->getCloudflareService();

        ServerSubdomain::where('server_id', $server->id)->each(function (ServerSubdomain $subdomain) use ($cloudflare) {
            if ($subdomain->record_id) {
                $cloudflare->deleteRecord($subdomain->record_id);
            }
            $subdomain->delete();
        });
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

        // If it's already an IP, return it
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Try to resolve hostname to IP
        $resolved = @gethostbyname($ip);
        if (filter_var($resolved, FILTER_VALIDATE_IP) && $resolved !== $ip) {
            return $resolved;
        }

        return null;
    }

    /**
     * Generate a unique subdomain slug from a server name.
     */
    private function generateUniqueSlug(string $name, string $domain): string
    {
        // Sanitize: lowercase, replace spaces/special chars with hyphens
        $slug = Str::slug($name, '-');
        $slug = substr($slug, 0, 63); // Max 63 chars for DNS label

        // Ensure uniqueness
        $original = $slug;
        $counter = 1;

        while (ServerSubdomain::where('subdomain', $slug)->where('domain', $domain)->exists()) {
            $slug = substr($original, 0, 60 - strlen((string) $counter)).'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function getBaseDomain(): ?string
    {
        return $this->settings->get('settings::subdomains:base_domain', null);
    }

    private function getApiToken(): ?string
    {
        return $this->settings->get('settings::subdomains:cloudflare_api_token', null);
    }

    private function getZoneId(): ?string
    {
        return $this->settings->get('settings::subdomains:cloudflare_zone_id', null);
    }
}
