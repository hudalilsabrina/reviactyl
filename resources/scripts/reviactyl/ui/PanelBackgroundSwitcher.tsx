import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApplicationStore } from '@/state';
import { useStoreActions, useStoreState } from 'easy-peasy';
import updateAccountPanelBackground from '@/api/account/updateAccountPanelBackground';
import Input from '@/reviactyl/elements/Input';

const PanelBackgroundSwitcher = () => {
    const { t } = useTranslation('dashboard/account');
    const user = useStoreState((state: ApplicationStore) => state.user.data);
    const setUserData = useStoreActions((actions: any) => actions.user.setUserData);
    const [value, setValue] = useState(user?.panelBackground || '');
    const [saving, setSaving] = useState(false);

    const apply = async (next: string | null) => {
        if (!user) return;
        setSaving(true);
        try {
            await updateAccountPanelBackground({ panelBackground: next });
            setUserData({ ...user, panelBackground: next });
            setValue(next || '');
        } catch (error) {
            console.error('Failed to update panel background:', error);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className='mb-2'>
            <div className='flex flex-col gap-2 sm:flex-row sm:justify-between sm:items-center'>
                <p className='min-w-0 flex-1'>{t('overview.panel-background')}</p>
                <div className='flex gap-2 w-full min-w-0 sm:w-auto'>
                    <Input
                        type='text'
                        className='flex-1'
                        placeholder='https://...'
                        value={value}
                        disabled={saving}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setValue(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') apply(value.trim() || null);
                        }}
                    />
                    <button
                        className='px-3 py-2 rounded-ui border border-gray-600 text-sm text-gray-200 hover:border-gray-400 disabled:opacity-50'
                        disabled={saving}
                        onClick={() => apply(value.trim() || null)}
                    >
                        {t('overview.panel-background-save')}
                    </button>
                    {user?.panelBackground && (
                        <button
                            className='px-3 py-2 rounded-ui border border-danger/50 text-sm text-danger/80 hover:border-danger disabled:opacity-50'
                            disabled={saving}
                            onClick={() => apply(null)}
                        >
                            {t('overview.panel-background-reset')}
                        </button>
                    )}
                </div>
            </div>
            <p className='mt-1 text-xs text-gray-400'>{t('overview.panel-background-helper')}</p>
        </div>
    );
};

export default PanelBackgroundSwitcher;
