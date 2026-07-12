import { createTypedHooks } from 'easy-peasy';
import type { ApplicationStore } from '@/state/index';
import type { Dispatch } from 'redux';

const {
    useStore,
    useStoreState,
    useStoreActions,
    useStoreDispatch: baseUseStoreDispatch,
} = createTypedHooks<ApplicationStore>();

export { useStore, useStoreState, useStoreActions };

// explicitly type this one to stop TS2742
export const useStoreDispatch: () => Dispatch<any> = baseUseStoreDispatch;
