import http from '@/api/http';
import { rawDataToServerSubdomain, ServerSubdomain } from '@/api/server/subdomain/getSubdomains';

export default async (uuid: string, subdomain: string, domain?: string): Promise<ServerSubdomain> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/subdomain`, {
        subdomain,
        domain: domain || undefined,
    });

    return rawDataToServerSubdomain(data);
};
