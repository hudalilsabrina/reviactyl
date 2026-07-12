import http from '@/api/http';

interface Data {
    language: string;
}

export default ({ language }: Data): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.put('/api/client/account/language', { language })
            .then(() => resolve())
            .catch(reject);
    });
};
