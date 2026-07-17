<?php

namespace App\Services\Plugins;

use App\Contracts\Repository\SettingsRepositoryInterface;
use App\Models\Server;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PluginProviderService
{
    public const PROVIDERS = ['modrinth', 'spiget', 'curseforge', 'hangar'];

    public function __construct(private SettingsRepositoryInterface $settings) {}

    /**
     * Provider API calls share one client so slow upstreams cannot hang a
     * request worker (Laravel's Http client has no default timeout).
     */
    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(10)->connectTimeout(5);
    }

    /**
     * Whether a provider can be used (CurseForge requires an admin-set API key).
     */
    public function isAvailable(string $provider): bool
    {
        return $provider !== 'curseforge' || ! empty($this->curseforgeKey());
    }

    /**
     * Search a provider for plugins. Returns a normalized list of projects plus
     * pagination metadata (total is null when the provider does not report it).
     *
     * @return array{results: array<int, array{id: string|int, slug: string|null, name: string, author: string|null, description: string|null, downloads: int|null, icon: string|null}>, total: int|null}
     */
    public function search(string $provider, string $query, Server $server, int $offset = 0): array
    {
        return match ($provider) {
            'modrinth' => $this->searchModrinth($query, $server, $offset),
            'spiget' => $this->searchSpiget($query, $offset),
            'curseforge' => $this->searchCurseForge($query, $server, $offset),
            'hangar' => $this->searchHangar($query, $server, $offset),
            default => ['results' => [], 'total' => null],
        };
    }

    /**
     * Get full project details. Returns null when not found.
     *
     * @return array{id: string|int, slug: string|null, name: string, author: string|null, description: string|null, body: string|null, body_html: string|null, downloads: int|null, icon: string|null, url: string|null}|null
     */
    public function details(string $provider, string $id): ?array
    {
        return match ($provider) {
            'modrinth' => $this->detailsModrinth($id),
            'spiget' => $this->detailsSpiget($id),
            'curseforge' => $this->detailsCurseForge($id),
            'hangar' => $this->detailsHangar($id),
            default => null,
        };
    }

    /**
     * Get installable versions for a project, newest first.
     *
     * @return array<int, array{id: string|int, name: string, game_versions: array<int, string>, downloads: int|null, date: string|null, url: string|null}>
     */
    public function versions(string $provider, string $id, Server $server): array
    {
        return match ($provider) {
            'modrinth' => $this->versionsModrinth($id, $server),
            'spiget' => $this->versionsSpiget($id),
            'curseforge' => $this->versionsCurseForge($id, $server),
            'hangar' => $this->versionsHangar($id, $server),
            default => [],
        };
    }

    /**
     * Resolve a direct download URL + filename for a version. Null when the
     * version cannot be downloaded directly (e.g. external Spigot resources).
     *
     * @return array{url: string, filename: string}|null
     */
    public function resolveDownload(string $provider, string $id, string $versionId): ?array
    {
        return match ($provider) {
            'modrinth' => $this->resolveModrinth($id, $versionId),
            'spiget' => $this->resolveSpiget($id, $versionId),
            'curseforge' => $this->resolveCurseForge($id, $versionId),
            'hangar' => $this->resolveHangar($id, $versionId),
            default => null,
        };
    }

    /**
     * Best-effort Minecraft version detection from common egg variables.
     */
    public function minecraftVersion(Server $server): ?string
    {
        $value = $server->variables()
            ->whereIn('env_variable', ['MINECRAFT_VERSION', 'MC_VERSION', 'MINECRAFT_RELEASE_TARGET'])
            ->value('server_value');

        $value = is_string($value) ? trim($value) : null;

        return $value !== null && $value !== '' && strtolower($value) !== 'latest' ? $value : null;
    }

    // ---------------------------------------------------------------------
    // Modrinth (docs.modrinth.com) — only "plugin" projects are exposed.
    // ---------------------------------------------------------------------

    private function searchModrinth(string $query, Server $server, int $offset): array
    {
        $facets = [['project_type:plugin']];
        if ($mc = $this->minecraftVersion($server)) {
            $facets[] = ['versions:'.$mc];
        }

        $response = $this->http()->get('https://api.modrinth.com/v2/search', [
            'query' => $query,
            'limit' => 20,
            'offset' => $offset,
            'facets' => json_encode($facets),
        ])
            ->throw();

        $results = array_map(fn ($hit) => [
            'id' => $hit['project_id'] ?? $hit['slug'],
            'slug' => $hit['slug'] ?? null,
            'name' => $hit['title'] ?? '',
            'author' => $hit['author'] ?? null,
            'description' => $hit['description'] ?? null,
            'downloads' => $hit['downloads'] ?? null,
            'icon' => $hit['icon_url'] ?? null,
        ], $response->json('hits') ?? []);

        return ['results' => $results, 'total' => $response->json('total_hits')];
    }

    private function detailsModrinth(string $id): ?array
    {
        $hit = $this->http()->get('https://api.modrinth.com/v2/project/'.urlencode($id));

        if (! $hit->successful()) {
            return null;
        }

        $data = $hit->json();

        return [
            'id' => $data['id'] ?? $id,
            'slug' => $data['slug'] ?? null,
            'name' => $data['title'] ?? '',
            'author' => $data['team'] ?? null,
            'description' => $data['description'] ?? null,
            'body' => $data['body'] ?? null,
            'body_html' => isset($data['body']) && $data['body'] !== '' ? Str::markdown($data['body']) : null,
            'downloads' => $data['downloads'] ?? null,
            'icon' => $data['icon_url'] ?? null,
            'url' => isset($data['slug']) ? 'https://modrinth.com/plugin/'.$data['slug'] : null,
        ];
    }

    private function versionsModrinth(string $id, Server $server): array
    {
        $params = [];
        if ($mc = $this->minecraftVersion($server)) {
            $params['game_versions'] = json_encode([$mc]);
        }

        $response = $this->http()->get('https://api.modrinth.com/v2/project/'.urlencode($id).'/version', $params);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json() ?? [];

        return array_values(array_map(fn ($v) => [
            'id' => $v['id'],
            'name' => $v['name'] ?? $v['version_number'] ?? '',
            'game_versions' => $v['game_versions'] ?? [],
            'downloads' => $v['downloads'] ?? null,
            'date' => $v['date_published'] ?? null,
            'url' => null,
        ], $data));
    }

    private function resolveModrinth(string $id, string $versionId): ?array
    {
        $response = $this->http()->get('https://api.modrinth.com/v2/version/'.urlencode($versionId));

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        $file = collect($data['files'] ?? [])->firstWhere('primary', true) ?? Arr::first($data['files'] ?? []);

        if (! $file || empty($file['url']) || empty($file['filename'])) {
            return null;
        }

        return ['url' => $file['url'], 'filename' => $file['filename']];
    }

    // ---------------------------------------------------------------------
    // Spiget (spiget.org) — public mirror of SpigotMC resources.
    // ---------------------------------------------------------------------

    private function searchSpiget(string $query, int $offset): array
    {
        $params = [
            'size' => 20,
            'page' => intdiv($offset, 20) + 1,
            'sort' => '-downloads',
            'fields' => 'id,name,tag,downloads,author,icon,premium,external',
        ];

        // The search endpoint 404s on an empty term; fall back to the resource listing.
        $data = $this->http()->get($query === ''
                ? 'https://api.spiget.org/v2/resources'
                : 'https://api.spiget.org/v2/search/resources/'.urlencode($query), $params)
            ->throw()
            ->json() ?? [];

        return [
            'results' => array_map(fn ($r) => [
                'id' => $r['id'],
                'slug' => null,
                'name' => $r['name'] ?? '',
                'author' => null, // Search only returns the author ID; the name is loaded in the details view.
                'description' => $r['tag'] ?? null,
                'downloads' => $r['downloads'] ?? null,
                'icon' => isset($r['icon']['url']) ? 'https://www.spigotmc.org/'.$r['icon']['url'] : null,
            ], $data),
            'total' => null, // Spiget search does not report a total count.
        ];
    }

    private function detailsSpiget(string $id): ?array
    {
        $response = $this->http()->get('https://api.spiget.org/v2/resources/'.urlencode($id));

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $author = $this->http()->get('https://api.spiget.org/v2/resources/'.urlencode($id).'/author')
            ->json('name');

        // Descriptions are base64-encoded HTML.
        $html = isset($data['description']) ? base64_decode((string) $data['description'], true) : false;

        return [
            'id' => $data['id'] ?? $id,
            'slug' => null,
            'name' => $data['name'] ?? '',
            'author' => $author,
            'description' => $data['tag'] ?? null,
            'body' => $html !== false ? trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '') ?: null : null,
            'body_html' => $html !== false && trim(strip_tags($html)) !== '' ? $html : null,
            'downloads' => $data['downloads'] ?? null,
            'icon' => isset($data['icon']['url']) ? 'https://www.spigotmc.org/'.$data['icon']['url'] : null,
            'url' => isset($data['id']) ? 'https://www.spigotmc.org/resources/'.$data['id'] : null,
        ];
    }

    private function versionsSpiget(string $id): array
    {
        $response = $this->http()->get('https://api.spiget.org/v2/resources/'.urlencode($id).'/versions', [
            'size' => 30,
            'sort' => '-releaseDate',
            'fields' => 'id,name,downloads,releaseDate',
        ]);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json() ?? [];

        return array_values(array_map(fn ($v) => [
            'id' => $v['id'],
            'name' => $v['name'] ?? (string) $v['id'],
            'game_versions' => [],
            'downloads' => $v['downloads'] ?? null,
            'date' => isset($v['releaseDate']) ? date('c', (int) $v['releaseDate']) : null,
            'url' => null,
        ], $data));
    }

    private function resolveSpiget(string $id, string $versionId): ?array
    {
        // External/premium resources cannot be downloaded automatically.
        $resource = $this->http()->get('https://api.spiget.org/v2/resources/'.urlencode($id))->json();

        if (($resource['external'] ?? false) || ($resource['premium'] ?? false)) {
            return null;
        }

        $url = 'https://api.spiget.org/v2/resources/'.urlencode($id).'/versions/'.urlencode($versionId).'/download';
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($resource['name'] ?? $id));

        return ['url' => $url, 'filename' => trim($name, '-').'.jar'];
    }

    // ---------------------------------------------------------------------
    // CurseForge (docs.curseforge.com) — requires an API key.
    // ---------------------------------------------------------------------

    private function curseforgeKey(): ?string
    {
        $key = $this->settings->get('settings::panel:plugins:curseforge_api_key', config('panel.plugins.curseforge_api_key'));

        return $key ? trim((string) $key) : null;
    }

    private function curseforge(): PendingRequest
    {
        return $this->http()->withHeaders(['x-api-key' => $this->curseforgeKey()]);
    }

    private function searchCurseForge(string $query, Server $server, int $offset): array
    {
        $params = [
            'gameId' => 432, // Minecraft
            'classId' => 5, // Bukkit plugins
            'searchFilter' => $query,
            'pageSize' => 20,
            'index' => $offset,
            'sortField' => 2, // popularity
            'sortOrder' => 'desc',
        ];
        if ($mc = $this->minecraftVersion($server)) {
            $params['gameVersion'] = $mc;
        }

        $response = $this->curseforge()
            ->get('https://api.curseforge.com/v1/mods/search', $params)
            ->throw();

        $results = array_map(fn ($m) => [
            'id' => $m['id'],
            'slug' => $m['slug'] ?? null,
            'name' => $m['name'] ?? '',
            'author' => Arr::get($m, 'authors.0.name'),
            'description' => $m['summary'] ?? null,
            'downloads' => isset($m['downloadCount']) ? (int) $m['downloadCount'] : null,
            'icon' => Arr::get($m, 'logo.thumbnailUrl'),
        ], $response->json('data') ?? []);

        return ['results' => $results, 'total' => $response->json('pagination.totalCount')];
    }

    private function detailsCurseForge(string $id): ?array
    {
        $response = $this->curseforge()->get('https://api.curseforge.com/v1/mods/'.urlencode($id));

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data');

        // The description endpoint returns HTML.
        $body = $this->curseforge()->get('https://api.curseforge.com/v1/mods/'.urlencode($id).'/description');
        $bodyHtml = $body->successful() ? trim((string) $body->json('data')) : null;

        return [
            'id' => $data['id'] ?? $id,
            'slug' => $data['slug'] ?? null,
            'name' => $data['name'] ?? '',
            'author' => Arr::get($data, 'authors.0.name'),
            'description' => $data['summary'] ?? null,
            'body' => $bodyHtml ? trim(preg_replace('/\s+/', ' ', strip_tags($bodyHtml)) ?? '') ?: null : null,
            'body_html' => $bodyHtml ?: null,
            'downloads' => isset($data['downloadCount']) ? (int) $data['downloadCount'] : null,
            'icon' => Arr::get($data, 'logo.thumbnailUrl'),
            'url' => Arr::get($data, 'links.websiteUrl'),
        ];
    }

    private function versionsCurseForge(string $id, Server $server): array
    {
        $params = ['pageSize' => 30];
        if ($mc = $this->minecraftVersion($server)) {
            $params['gameVersion'] = $mc;
        }

        $response = $this->curseforge()
            ->get('https://api.curseforge.com/v1/mods/'.urlencode($id).'/files', $params);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json('data') ?? [];

        return array_values(array_map(fn ($f) => [
            'id' => $f['id'],
            'name' => $f['displayName'] ?? $f['fileName'] ?? '',
            'game_versions' => $f['gameVersions'] ?? [],
            'downloads' => $f['downloadCount'] ?? null,
            'date' => $f['fileDate'] ?? null,
            'url' => null,
        ], $data));
    }

    private function resolveCurseForge(string $id, string $versionId): ?array
    {
        $response = $this->curseforge()
            ->get('https://api.curseforge.com/v1/mods/'.urlencode($id).'/files/'.urlencode($versionId));

        if (! $response->successful()) {
            return null;
        }

        $file = $response->json('data');

        $url = $file['downloadUrl'] ?? null;
        $filename = $file['fileName'] ?? null;

        // Some authors block third-party downloads; fall back to the CDN URL.
        if (! $url && ! empty($file['fileName']) && isset($file['id'])) {
            $fid = (string) $file['id'];
            $url = sprintf(
                'https://edge.forgecdn.net/files/%s/%s/%s',
                substr($fid, 0, 4),
                substr($fid, 4),
                $file['fileName']
            );
        }

        if (! $url || ! $filename) {
            return null;
        }

        return ['url' => $url, 'filename' => $filename];
    }

    // ---------------------------------------------------------------------
    // Hangar (hangar.papermc.io) — Paper's plugin repository.
    // ---------------------------------------------------------------------

    private function searchHangar(string $query, Server $server, int $offset): array
    {
        $response = $this->http()->get('https://hangar.papermc.io/api/v1/projects', [
            'query' => $query,
            'limit' => 20,
            'offset' => $offset,
        ])
            ->throw();

        $results = array_map(fn ($p) => [
            'id' => $p['namespace']['slug'] ?? $p['name'],
            'slug' => $p['namespace']['slug'] ?? null,
            'name' => $p['name'] ?? '',
            'author' => $p['namespace']['owner'] ?? null,
            'description' => $p['description'] ?? null,
            'downloads' => Arr::get($p, 'stats.downloads'),
            'icon' => $p['avatarUrl'] ?? null,
        ], $response->json('result') ?? []);

        return ['results' => $results, 'total' => $response->json('pagination.count')];
    }

    private function detailsHangar(string $id): ?array
    {
        $response = $this->http()->get('https://hangar.papermc.io/api/v1/projects/'.urlencode($id));

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        // The main page is markdown served as text/plain.
        $page = $this->http()->withHeaders(['Accept' => 'text/plain'])->get('https://hangar.papermc.io/api/v1/pages/main/'.urlencode($data['namespace']['owner'] ?? '').'/'.urlencode($data['namespace']['slug'] ?? $id));
        $markdown = $page->successful() ? trim($page->body()) : null;

        return [
            'id' => $data['namespace']['slug'] ?? $id,
            'slug' => $data['namespace']['slug'] ?? null,
            'name' => $data['name'] ?? '',
            'author' => $data['namespace']['owner'] ?? null,
            'description' => $data['description'] ?? null,
            'body' => $markdown ? trim(preg_replace('/[#*_`>\[\]()!-]+/', ' ', strip_tags(Str::markdown($markdown))) ?? '') ?: null : null,
            'body_html' => $markdown ? Str::markdown($markdown) : null,
            'downloads' => Arr::get($data, 'stats.downloads'),
            'icon' => $data['avatarUrl'] ?? null,
            'url' => isset($data['namespace']['slug']) ? 'https://hangar.papermc.io/'.$data['namespace']['owner'].'/'.$data['namespace']['slug'] : null,
        ];
    }

    private function versionsHangar(string $id, Server $server): array
    {
        $response = $this->http()->get('https://hangar.papermc.io/api/v1/projects/'.urlencode($id).'/versions', ['limit' => 30]);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json('result') ?? [];

        $mc = $this->minecraftVersion($server);
        $versions = array_map(fn ($v) => [
            'id' => $v['name'],
            'name' => $v['name'],
            'game_versions' => collect($v['platformDependencies'] ?? [])->flatten()->unique()->values()->all(),
            'downloads' => collect($v['stats']['platformDownloads'] ?? [])->sum(),
            'date' => $v['createdAt'] ?? null,
            'url' => null,
        ], $data);

        if ($mc) {
            $filtered = array_values(array_filter($versions, fn ($v) => in_array($mc, $v['game_versions'], true)));

            return $filtered ?: $versions;
        }

        return $versions;
    }

    private function resolveHangar(string $id, string $versionId): ?array
    {
        $response = $this->http()->get('https://hangar.papermc.io/api/v1/projects/'.urlencode($id).'/versions/'.urlencode($versionId));

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        $url = Arr::get($data, 'downloads.PAPER.downloadUrl')
            ?? Arr::get($data, 'downloads.PAPER.externalUrl')
            ?? collect($data['downloads'] ?? [])->pluck('downloadUrl')->filter()->first();

        if (! $url) {
            return null;
        }

        $filename = Arr::get($data, 'downloads.PAPER.fileInfo.name') ?? ($id.'-'.$versionId.'.jar');

        return ['url' => $url, 'filename' => $filename];
    }
}
