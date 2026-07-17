import http from '@/api/http';

export interface ConfigRevision {
    id: number;
    hash: string;
    message: string;
    author: { uuid: string | null; username: string };
    file_count: number;
    is_preset: boolean;
    preset_name: string | null;
    files: string[];
    created_at: string;
}

export interface ConfigDiffFile {
    status: 'added' | 'deleted' | 'modified';
    additions: number;
    deletions: number;
    hunks: Array<{
        old_start: number;
        old_lines: number;
        new_start: number;
        new_lines: number;
        lines: string[];
    }>;
}

export interface ConfigDiff {
    revision_from: number | string;
    revision_to: number | string;
    files: Record<string, ConfigDiffFile>;
}

export interface WatchPatternsResponse {
    patterns: string[];
    is_custom: boolean;
    defaults?: string[];
}

export const getRevisions = async (uuid: string, page = 1, perPage = 25, presetsOnly = false) => {
    const { data } = await http.get(`/api/client/servers/${uuid}/config-revisions`, {
        params: { page, per_page: perPage, preset_only: presetsOnly },
    });

    return {
        ...data,
        data: (data.data ?? []).map((item: { object: string; attributes: ConfigRevision }) => item.attributes),
    };
};

export const getRevisionDetail = async (
    uuid: string,
    revisionId: number
): Promise<ConfigRevision> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/config-revisions/${revisionId}`);
    return data.attributes ?? data;
};

export const getRevisionFiles = async (uuid: string, revisionId: number) => {
    const { data } = await http.get(`/api/client/servers/${uuid}/config-revisions/${revisionId}/files`);
    return (data.files ?? []) as Array<{
        path: string;
        content_hash: string;
        content_length: number;
        changed_in_revision: boolean;
    }>;
};

export const getFileAtRevision = async (uuid: string, revisionId: number, path: string): Promise<string> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/config-revisions/${revisionId}/file`, {
        params: { path },
        responseType: 'text',
    });
    return data;
};

export const compareRevisions = async (uuid: string, revisionA: number, revisionB: number): Promise<ConfigDiff> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/config-revisions/${revisionA}/diff/${revisionB}`);
    return data.attributes;
};

export const diffAgainstCurrent = async (uuid: string, revisionId: number): Promise<ConfigDiff> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/config-revisions/${revisionId}/diff-current`);
    return data.attributes;
};

export const createSnapshot = async (uuid: string, message?: string, files?: string[]): Promise<ConfigRevision> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/config-revisions`, { message, files });
    return data.attributes ?? data;
};

export const revertToRevision = async (
    uuid: string,
    revisionId: number,
    files?: string[],
    message?: string
): Promise<ConfigRevision> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/config-revisions/${revisionId}/revert`, {
        files,
        message,
    });
    return data.attributes ?? data;
};

export const promoteToPreset = async (uuid: string, revisionId: number, name: string): Promise<ConfigRevision> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/config-revisions/${revisionId}/promote`, { name });
    return data.attributes ?? data;
};

export const activatePreset = async (uuid: string, presetName: string): Promise<ConfigRevision> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/config-revisions/presets/${presetName}/activate`);
    return data.attributes ?? data;
};

export const deletePreset = async (uuid: string, presetName: string) => {
    await http.delete(`/api/client/servers/${uuid}/config-revisions/presets/${presetName}`);
};

export const getWatchPatterns = async (uuid: string): Promise<WatchPatternsResponse> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/config-revisions/watch-patterns`);
    return data;
};

export const updateWatchPatterns = async (uuid: string, patterns: string[]): Promise<WatchPatternsResponse> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/config-revisions/watch-patterns`, { patterns });
    return data;
};

export const resetWatchPatterns = async (uuid: string): Promise<WatchPatternsResponse> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/config-revisions/watch-patterns/reset`);
    return data;
};
