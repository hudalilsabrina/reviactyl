import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
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
import { formatCount, formatGameVersions } from '@/components/server/plugins/format';

// ponytail: Trust boundary — provider markup is untrusted, always sanitize before injecting.
const sanitizeBody = (html: string): string =>
    DOMPurify.sanitize(marked.parse(html, { async: false }) as string, {
        USE_PROFILES: { html: true },
        FORBID_TAGS: ['style', 'form', 'input', 'button', 'iframe', 'video', 'audio', 'object', 'embed'],
        FORBID_ATTR: ['style'],
        ADD_ATTR: ['target', 'rel'],
    });

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

    const bodyHtml = useMemo(() => (details?.body_html ? sanitizeBody(details.body_html) : null), [details]);

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
            <div className='flex items-start gap-3 sm:gap-4'>
                {plugin.icon ? (
                    <img
                        src={plugin.icon}
                        alt=''
                        className='w-12 h-12 sm:w-16 sm:h-16 rounded-ui object-cover flex-none'
                    />
                ) : (
                    <div className='w-12 h-12 sm:w-16 sm:h-16 rounded-ui bg-gray-800 flex items-center justify-center flex-none'>
                        <FaPuzzlePiece className='w-5 h-5 sm:w-6 sm:h-6 text-gray-500' />
                    </div>
                )}
                <div className='min-w-0 flex-1'>
                    <h2 className='text-lg sm:text-xl font-semibold text-gray-100 break-words'>{plugin.name}</h2>
                    {details?.author && <p className='text-sm text-gray-400 truncate'>{details.author}</p>}
                    <div className='flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-xs text-gray-500'>
                        {details?.downloads !== null && details?.downloads !== undefined && (
                            <span
                                className='inline-flex items-center gap-1.5 font-mono'
                                title={details.downloads.toLocaleString()}
                            >
                                <FaDownload /> {formatCount(details.downloads)}
                            </span>
                        )}
                        {details?.url && (
                            <a
                                href={details.url}
                                target='_blank'
                                rel='noreferrer'
                                className='inline-flex items-center gap-1.5 text-primary-400 hover:text-primary-300 hover:underline underline-offset-2'
                            >
                                <FaExternalLinkAlt className='w-3 h-3' /> {t('view-on-provider')}
                            </a>
                        )}
                    </div>
                </div>
            </div>

            <div className='mt-4 max-h-72 sm:max-h-80 overflow-y-auto pr-1'>
                {!details ? (
                    <Spinner size='base' centered />
                ) : bodyHtml ? (
                    <div className='plugin-body text-sm text-gray-300' dangerouslySetInnerHTML={{ __html: bodyHtml }} />
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
                        <Select
                            value={selectedVersion}
                            onChange={(e) => setSelectedVersion(e.target.value)}
                            className='font-mono text-sm'
                        >
                            {versions.map((v) => {
                                const supported = formatGameVersions(v.game_versions);

                                return (
                                    <option key={v.id} value={String(v.id)}>
                                        {v.name}
                                        {supported.length > 0 ? ` (${supported.join(', ')})` : ''}
                                    </option>
                                );
                            })}
                        </Select>
                    )}
                </div>
                <Button
                    onClick={install}
                    disabled={!selectedVersion || installing}
                    isLoading={installing}
                    className='sm:w-auto w-full flex-none'
                >
                    {t('install')}
                </Button>
            </div>
            <p className='mt-2 text-xs text-gray-600'>{t('install-hint')}</p>
        </Modal>
    );
};
