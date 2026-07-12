import http from '@/api/http';

export interface SocialLogin {
    id: number;
    provider: string;
    createdAt: Date;
    updatedAt: Date;
}

export const rawDataToSocialLogin = (data: any): SocialLogin => ({
    id: data.id,
    provider: data.provider,
    createdAt: new Date(data.created_at),
    updatedAt: new Date(data.updated_at),
});

export default (): Promise<SocialLogin[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/account/social-logins')
            .then(({ data }) => resolve((data.data || []).map((d: any) => rawDataToSocialLogin(d.attributes))))
            .catch(reject);
    });
};
