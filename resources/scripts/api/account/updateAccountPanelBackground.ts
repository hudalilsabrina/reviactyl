import http from '@/api/http';

interface Data {
    panelBackground: string | null;
}

export default ({ panelBackground }: Data): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.put('/api/client/account/panel-background', { panelBackground })
            .then(() => resolve())
            .catch(reject);
    });
};
