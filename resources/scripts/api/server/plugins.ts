import http from '@/api/http';

export type PluginProvider = 'modrinth' | 'spiget' | 'curseforge' | 'hangar';

export interface PluginSearchResult {
    id: string | number;
    slug: string | null;
    name: string;
    author: string | null;
    description: string | null;
    downloads: number | null;
    icon: string | null;
}

export interface PluginDetails extends PluginSearchResult {
    body: string | null;
    /** Raw provider markup (HTML or rendered markdown); sanitize before injecting into the DOM. */
    body_html: string | null;
    url: string | null;
}

export interface PluginVersion {
    id: string | number;
    name: string;
    game_versions: string[];
    downloads: number | null;
    date: string | null;
}

export const searchPlugins = async (
    uuid: string,
    provider: PluginProvider,
    query: string,
    page = 1
): Promise<{ results: PluginSearchResult[]; minecraftVersion: string | null; total: number | null }> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/plugins/search`, {
        params: { provider, query, page },
    });

    return {
        results: data.data,
        minecraftVersion: data.meta?.minecraft_version ?? null,
        total: data.meta?.total ?? null,
    };
};

export const getPluginDetails = async (
    uuid: string,
    provider: PluginProvider,
    id: string | number
): Promise<PluginDetails> => {
    const { data } = await http.get(
        `/api/client/servers/${uuid}/plugins/${provider}/${encodeURIComponent(String(id))}`
    );

    return data.data;
};

export const getPluginVersions = async (
    uuid: string,
    provider: PluginProvider,
    id: string | number
): Promise<PluginVersion[]> => {
    const { data } = await http.get(
        `/api/client/servers/${uuid}/plugins/${provider}/${encodeURIComponent(String(id))}/versions`
    );

    return data.data;
};

export const installPlugin = async (
    uuid: string,
    provider: PluginProvider,
    id: string | number,
    versionId: string | number
): Promise<string> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/plugins/install`, {
        provider,
        id: String(id),
        version_id: String(versionId),
    });

    return data.data.filename;
};
