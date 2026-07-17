import http from '@/api/http';

export default async (uuid: string, subdomainId: number): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/subdomain/${subdomainId}`);
};
