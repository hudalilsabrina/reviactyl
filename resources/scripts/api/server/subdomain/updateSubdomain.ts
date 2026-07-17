import http from '@/api/http';
import { rawDataToServerSubdomain, ServerSubdomain } from '@/api/server/subdomain/getSubdomains';

export default async (uuid: string, subdomainId: number, subdomain: string): Promise<ServerSubdomain> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/subdomain/${subdomainId}`, {
        subdomain,
    });

    return rawDataToServerSubdomain(data.data);
};
