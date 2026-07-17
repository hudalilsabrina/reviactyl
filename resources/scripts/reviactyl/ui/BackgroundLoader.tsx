import { useEffect } from 'react';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';

export const BackgroundLoader = () => {
    const panelBackground = useStoreState((state: ApplicationStore) => state.user.data?.panelBackground);

    useEffect(() => {
        if (typeof document === 'undefined') return;
        const root = document.documentElement;
        if (panelBackground) {
            root.style.setProperty('--background', `url(${panelBackground})`);
        } else {
            root.style.removeProperty('--background');
        }
    }, [panelBackground]);

    return null;
};
