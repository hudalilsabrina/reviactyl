import http from '@/api/http';

interface PartChoice {
    part_id: number;
    enabled: boolean;
}

export default async (uuid: string, parts: PartChoice[]): Promise<string> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/startup/parts`, { parts });

    return data.meta.startup_command;
};
