import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Modal from '@/reviactyl/elements/Modal';
import Select from '@/reviactyl/elements/Select';
import Spinner from '@/reviactyl/elements/Spinner';
import Button from '@/reviactyl/elements/Button';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import {
    getPluginDetails,
    getPluginVersions,
    installPlugin,
    PluginDetails,
    PluginProvider,
    PluginSearchResult,
    PluginVersion,
} from '@/api/server/plugins';
import { FaDownload, FaExternalLinkAlt, FaPuzzlePiece } from 'react-icons/fa';

interface Props {
    uuid: string;
    provider: PluginProvider;
    plugin: PluginSearchResult;
    visible: boolean;
    onDismissed: () => void;
    onInstalled: () => void;
}

export default ({ uuid, provider, plugin, visible, onDismissed, onInstalled }: Props) => {
    const { t } = useTranslation('server/plugins');
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();
    const [details, setDetails] = useState<PluginDetails | null>(null);
    const [versions, setVersions] = useState<PluginVersion[]>([]);
    const [selectedVersion, setSelectedVersion] = useState<string>('');
    const [installing, setInstalling] = useState(false);

    useEffect(() => {
        if (!visible) return;

        clearFlashes('server:plugins');
        setDetails(null);
        setVersions([]);
        setSelectedVersion('');

        getPluginDetails(uuid, provider, plugin.id)
            .then(setDetails)
            .catch((error) => clearAndAddHttpError({ key: 'server:plugins', error }));

        getPluginVersions(uuid, provider, plugin.id)
            .then((data) => {
                setVersions(data);
                if (data[0]) setSelectedVersion(String(data[0].id));
            })
            .catch((error) => clearAndAddHttpError({ key: 'server:plugins', error }));
    }, [visible]);

    const install = () => {
        if (!selectedVersion) return;

        setInstalling(true);
        installPlugin(uuid, provider, plugin.id, selectedVersion)
            .then((filename) => {
                addFlash({
                    key: 'server:plugins',
                    type: 'success',
                    message: t('install-success', { name: plugin.name, file: filename }),
                });
                setInstalling(false);
                onInstalled();
                onDismissed();
            })
            .catch((error) => {
                clearAndAddHttpError({ key: 'server:plugins', error });
                setInstalling(false);
            });
    };

    return (
        <Modal visible={visible} onDismissed={onDismissed} size='lg' appear>
            <FlashMessageRender byKey={'server:plugins'} className='mb-4' />
            <div className='flex items-start gap-4'>
                {plugin.icon ? (
                    <img src={plugin.icon} alt='' className='w-16 h-16 rounded-ui object-cover flex-none' />
                ) : (
                    <div className='w-16 h-16 rounded-ui bg-gray-800 flex items-center justify-center flex-none'>
                        <FaPuzzlePiece className='w-6 h-6 text-gray-500' />
                    </div>
                )}
                <div className='min-w-0'>
                    <h2 className='text-xl font-semibold text-gray-100 truncate'>{plugin.name}</h2>
                    {details?.author && <p className='text-sm text-gray-400'>{details.author}</p>}
                    <div className='flex items-center gap-4 mt-1 text-xs text-gray-500'>
                        {details?.downloads !== null && details?.downloads !== undefined && (
                            <span className='inline-flex items-center gap-1'>
                                <FaDownload /> {details.downloads.toLocaleString()}
                            </span>
                        )}
                        {details?.url && (
                            <a
                                href={details.url}
                                target='_blank'
                                rel='noreferrer'
                                className='inline-flex items-center gap-1 text-primary-400 hover:text-primary-300'
                            >
                                <FaExternalLinkAlt /> {t('view-on-provider')}
                            </a>
                        )}
                    </div>
                </div>
            </div>

            <div className='mt-4 max-h-64 overflow-y-auto pr-1'>
                {!details ? (
                    <Spinner size='base' centered />
                ) : (
                    <p className='text-sm text-gray-300 whitespace-pre-line'>
                        {details.body ?? details.description ?? t('no-description')}
                    </p>
                )}
            </div>

            <div className='mt-6 flex flex-col sm:flex-row items-stretch sm:items-end gap-3'>
                <div className='flex-1'>
                    <label className='block text-xs font-semibold text-gray-400 mb-1'>{t('version-label')}</label>
                    {versions.length === 0 ? (
                        <Spinner size='small' />
                    ) : (
                        <Select value={selectedVersion} onChange={(e) => setSelectedVersion(e.target.value)}>
                            {versions.map((v) => (
                                <option key={v.id} value={String(v.id)}>
                                    {v.name}
                                    {v.game_versions.length > 0 ? ` (${v.game_versions.slice(0, 4).join(', ')})` : ''}
                                </option>
                            ))}
                        </Select>
                    )}
                </div>
                <Button
                    onClick={install}
                    disabled={!selectedVersion || installing}
                    isLoading={installing}
                    className='sm:w-auto w-full'
                >
                    {t('install')}
                </Button>
            </div>
            <p className='mt-2 text-xs text-gray-600'>{t('install-hint')}</p>
        </Modal>
    );
};
