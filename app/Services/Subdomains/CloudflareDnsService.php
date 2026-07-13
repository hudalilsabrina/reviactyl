<?php

namespace App\Services\Subdomains;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareDnsService
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    private const TIMEOUT = 10;

    public function __construct(
        private readonly string $apiToken,
        private readonly string $zoneId,
    ) {}

    /**
     * Create a DNS A record in Cloudflare.
     *
     * @return string|null The Cloudflare record ID, or null on failure
     */
    public function createARecord(string $name, string $content, bool $proxied = false): ?string
    {
        $response = Http::timeout(self::TIMEOUT)->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])->post(self::API_BASE."/zones/{$this->zoneId}/dns_records", [
            'type' => 'A',
            'name' => $name,
            'content' => $content,
            'ttl' => 1,
            'proxied' => $proxied,
        ]);

        if ($response->successful() && $response->json('success')) {
            return $response->json('result.id');
        }

        Log::error('Failed to create Cloudflare DNS record', [
            'name' => $name,
            'content' => $content,
            'status' => $response->status(),
            'errors' => $response->json('errors'),
        ]);

        return null;
    }

    /**
     * Delete a DNS record from Cloudflare.
     * Returns true if deleted or already gone (404).
     */
    public function deleteRecord(string $recordId): bool
    {
        $response = Http::timeout(self::TIMEOUT)->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])->delete(self::API_BASE."/zones/{$this->zoneId}/dns_records/{$recordId}");

        if ($response->successful() && $response->json('success')) {
            return true;
        }

        // Record already deleted from Cloudflare — treat as success
        if ($response->status() === 404) {
            return true;
        }

        Log::error('Failed to delete Cloudflare DNS record', [
            'record_id' => $recordId,
            'status' => $response->status(),
            'errors' => $response->json('errors'),
        ]);

        return false;
    }

    /**
     * Check if a DNS record name already exists in Cloudflare.
     */
    public function recordExists(string $name): bool
    {
        $response = Http::timeout(self::TIMEOUT)->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->get(self::API_BASE."/zones/{$this->zoneId}/dns_records", [
            'name' => $name,
            'type' => 'A',
        ]);

        if (! $response->successful() || ! $response->json('success')) {
            // On lookup failure, assume record doesn't exist to allow creation attempt
            Log::warning('Cloudflare recordExists check failed', [
                'name' => $name,
                'status' => $response->status(),
            ]);

            return false;
        }

        return $response->json('result_info.total_count', 0) > 0;
    }
}
