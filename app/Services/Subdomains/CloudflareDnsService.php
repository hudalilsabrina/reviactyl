<?php

namespace App\Services\Subdomains;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareDnsService
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

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
        $response = Http::withHeaders([
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
            'errors' => $response->json('errors'),
        ]);

        return null;
    }

    /**
     * Delete a DNS record from Cloudflare.
     */
    public function deleteRecord(string $recordId): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Content-Type' => 'application/json',
        ])->delete(self::API_BASE."/zones/{$this->zoneId}/dns_records/{$recordId}");

        if ($response->successful() && $response->json('success')) {
            return true;
        }

        Log::error('Failed to delete Cloudflare DNS record', [
            'record_id' => $recordId,
            'errors' => $response->json('errors'),
        ]);

        return false;
    }

    /**
     * Check if a DNS record name already exists in Cloudflare.
     */
    public function recordExists(string $name): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->get(self::API_BASE."/zones/{$this->zoneId}/dns_records", [
            'name' => $name,
            'type' => 'A',
        ]);

        return $response->successful()
            && $response->json('success')
            && $response->json('result_info.total_count', 0) > 0;
    }
}
