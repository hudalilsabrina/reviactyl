import http from '@/api/http';

export default (provider: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/account/social-logins/${provider}`)
            .then(() => resolve())
            .catch(reject);
    });
};
