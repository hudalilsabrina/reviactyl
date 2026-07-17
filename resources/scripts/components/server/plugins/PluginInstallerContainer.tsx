import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import ServerContentBlock from '@/reviactyl/elements/ServerContentBlock';
import Card from '@/reviactyl/ui/Card';
import Input from '@/reviactyl/elements/Input';
import Spinner from '@/reviactyl/elements/Spinner';
import useFlash from '@/plugins/useFlash';
import Can from '@/reviactyl/elements/Can';
import { ServerContext } from '@/state/server';
import { PluginProvider, PluginSearchResult, searchPlugins } from '@/api/server/plugins';
import PluginDetailsModal from '@/components/server/plugins/PluginDetailsModal';
import { FaDownload, FaPuzzlePiece, FaSearch } from 'react-icons/fa';
import classNames from 'classnames';

const PROVIDERS: { id: PluginProvider; label: string }[] = [
    { id: 'modrinth', label: 'Modrinth' },
    { id: 'spiget', label: 'Spigot' },
    { id: 'curseforge', label: 'CurseForge' },
    { id: 'hangar', label: 'Hangar' },
];

const PluginCard = ({ plugin, onClick }: { plugin: PluginSearchResult; onClick: () => void }) => (
    <button
        onClick={onClick}
        className='text-left rounded-ui bg-gray-900 border border-gray-800 p-4 flex gap-3 transition-colors duration-150 hover:border-primary-500/60 focus:outline-none focus:border-primary-500'
    >
        {plugin.icon ? (
            <img src={plugin.icon} alt='' className='w-12 h-12 rounded-ui object-cover flex-none' />
        ) : (
            <div className='w-12 h-12 rounded-ui bg-gray-800 flex items-center justify-center flex-none'>
                <FaPuzzlePiece className='w-5 h-5 text-gray-500' />
            </div>
        )}
        <div className='min-w-0 flex-1'>
            <p className='text-sm font-semibold text-gray-100 truncate'>{plugin.name}</p>
            {plugin.author && <p className='text-xs text-gray-500 truncate'>{plugin.author}</p>}
            {plugin.description && <p className='text-xs text-gray-400 mt-1 line-clamp-2'>{plugin.description}</p>}
            {plugin.downloads !== null && plugin.downloads !== undefined && (
                <p className='text-xs text-gray-500 mt-2 inline-flex items-center gap-1'>
                    <FaDownload className='w-3 h-3' /> {plugin.downloads.toLocaleString()}
                </p>
            )}
        </div>
    </button>
);

export default () => {
    const { t } = useTranslation('server/plugins');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    const [provider, setProvider] = useState<PluginProvider>('modrinth');
    const [inputValue, setInputValue] = useState('');
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PluginSearchResult[] | null>(null);
    const [minecraftVersion, setMinecraftVersion] = useState<string | null>(null);
    const [selected, setSelected] = useState<PluginSearchResult | null>(null);
    const searchGenRef = useRef(0);

    useEffect(() => {
        const timer = setTimeout(() => setQuery(inputValue.trim()), 350);
        return () => clearTimeout(timer);
    }, [inputValue]);

    useEffect(() => {
        clearFlashes('server:plugins');
        setResults(null);

        const gen = ++searchGenRef.current;
        searchPlugins(uuid, provider, query)
            .then(({ results, minecraftVersion }) => {
                if (gen !== searchGenRef.current) return;
                setResults(results);
                setMinecraftVersion(minecraftVersion);
            })
            .catch((error) => {
                if (gen !== searchGenRef.current) return;
                setResults([]);
                clearAndAddHttpError({ key: 'server:plugins', error });
            });
    }, [provider, query]);

    return (
        <ServerContentBlock title={t('title')} showFlashKey={'server:plugins'}>
            <Card className='flex flex-col gap-3 mb-1 mt-2'>
                <div className='flex flex-wrap items-center gap-2'>
                    <div className='flex flex-wrap gap-1'>
                        {PROVIDERS.map((p) => (
                            <button
                                key={p.id}
                                onClick={() => setProvider(p.id)}
                                className={classNames(
                                    'px-3 py-1.5 text-sm rounded-ui border transition-colors duration-150',
                                    provider === p.id
                                        ? 'bg-primary-500/80 border-primary-600/80 text-primary-50'
                                        : 'bg-gray-800 border-gray-700 text-gray-300 hover:border-gray-500'
                                )}
                            >
                                {p.label}
                            </button>
                        ))}
                    </div>
                    <div className='relative flex-1 min-w-[200px]'>
                        <FaSearch className='absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-500 pointer-events-none' />
                        <Input
                            type='text'
                            className='pl-8'
                            placeholder={t('search-placeholder', { provider: provider })}
                            value={inputValue}
                            onChange={(e) => setInputValue(e.target.value)}
                        />
                    </div>
                </div>
                {minecraftVersion && (
                    <p className='text-xs text-gray-500'>{t('filtered-for', { version: minecraftVersion })}</p>
                )}
            </Card>

            {!results ? (
                <Spinner size='large' centered />
            ) : results.length === 0 ? (
                <Card>
                    <div className='flex flex-col items-center justify-center py-10 text-gray-600'>
                        <FaPuzzlePiece className='w-10 h-10 mb-2 opacity-40' />
                        <p className='text-sm'>{t('no-results')}</p>
                    </div>
                </Card>
            ) : (
                <motion.div
                    key={provider}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 0.15, ease: 'easeIn' }}
                    className='grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3'
                >
                    {results.map((plugin) => (
                        <Can action={'file.create'} key={`${provider}:${plugin.id}`}>
                            <PluginCard plugin={plugin} onClick={() => setSelected(plugin)} />
                        </Can>
                    ))}
                </motion.div>
            )}

            {selected && (
                <PluginDetailsModal
                    uuid={uuid}
                    provider={provider}
                    plugin={selected}
                    visible
                    onDismissed={() => setSelected(null)}
                    onInstalled={() => undefined}
                />
            )}
        </ServerContentBlock>
    );
};
