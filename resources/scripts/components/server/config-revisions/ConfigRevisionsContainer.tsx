import { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/reviactyl/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/reviactyl/elements/Spinner';
import Button from '@/reviactyl/elements/Button';
import Can from '@/reviactyl/elements/Can';
import Card from '@/reviactyl/ui/Card';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import {
    getRevisions,
    createSnapshot,
    revertToRevision,
    promoteToPreset,
    activatePreset,
    deletePreset,
    compareRevisions,
    diffAgainstCurrent,
    ConfigRevision,
    ConfigDiff,
} from '@/api/server/configRevisions';
import RevisionRow from '@/components/server/config-revisions/RevisionRow';
import DiffViewer from '@/components/server/config-revisions/DiffViewer';
import CreateSnapshotModal from '@/components/server/config-revisions/CreateSnapshotModal';
import PresetManager from '@/components/server/config-revisions/PresetManager';
import CompareSelector from '@/components/server/config-revisions/CompareSelector';
import WatchPatternsManager from '@/components/server/config-revisions/WatchPatternsManager';

const ConfigRevisionsContainer = () => {
    const { t } = useTranslation('server/config-revisions');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearAndAddHttpError, clearFlashes } = useFlash();

    const [revisions, setRevisions] = useState<ConfigRevision[]>([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showCompareSelector, setShowCompareSelector] = useState(false);
    const [showWatchPatterns, setShowWatchPatterns] = useState(false);

    const [selectedRevision, setSelectedRevision] = useState<ConfigRevision | null>(null);
    const [diff, setDiff] = useState<ConfigDiff | null>(null);
    const [diffLoading, setDiffLoading] = useState(false);

    const fetchRevisions = async () => {
        setLoading(true);
        clearFlashes('config-revisions');

        try {
            const data = await getRevisions(uuid, page);
            setRevisions(data.data || []);
            setTotalPages(data.last_page || 1);
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRevisions();
    }, [uuid, page]);

    const handleCreateSnapshot = async (message?: string, files?: string[]) => {
        clearFlashes('config-revisions');

        try {
            await createSnapshot(uuid, message, files);
            setShowCreateModal(false);
            fetchRevisions();
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
            throw error;
        }
    };

    const handleRevert = async (revision: ConfigRevision) => {
        if (!confirm('Revert to this revision? This will overwrite current files.')) return;

        try {
            await revertToRevision(uuid, revision.id);
            fetchRevisions();
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        }
    };

    const handlePromote = async (revision: ConfigRevision, name: string) => {
        try {
            await promoteToPreset(uuid, revision.id, name);
            fetchRevisions();
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        }
    };

    const handleActivatePreset = async (presetName: string) => {
        if (!confirm(`Activate preset "${presetName}"? This will overwrite current files.`)) return;

        try {
            await activatePreset(uuid, presetName);
            fetchRevisions();
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        }
    };

    const handleDeletePreset = async (presetName: string) => {
        if (!confirm(`Delete preset "${presetName}"? The revision will be kept but untagged.`)) return;

        try {
            await deletePreset(uuid, presetName);
            fetchRevisions();
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        }
    };

    const handleViewDiff = async (revision: ConfigRevision) => {
        if (selectedRevision?.id === revision.id) {
            setSelectedRevision(null);
            setDiff(null);
            return;
        }

        setSelectedRevision(revision);
        setDiffLoading(true);

        try {
            const diffData = await diffAgainstCurrent(uuid, revision.id);
            setDiff(diffData);
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        } finally {
            setDiffLoading(false);
        }
    };

    const handleCompare = async (revisionA: number, revisionB: number) => {
        setShowCompareSelector(false);
        setDiffLoading(true);

        try {
            const diffData = await compareRevisions(uuid, revisionA, revisionB);
            setDiff(diffData);
            setSelectedRevision(revisions.find((r) => r.id === revisionA) || null);
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        } finally {
            setDiffLoading(false);
        }
    };

    const presets = revisions.filter((r) => r.is_preset);

    return (
        <ServerContentBlock title={t('title', 'Config Revisions')}>
            <FlashMessageRender byKey={'config-revisions'} css={tw`mb-4`} />

            <div css={tw`flex flex-wrap items-center gap-2 mb-4`}>
                <Can action={'config-revision.create'}>
                    <Button size={'small'} onClick={() => setShowCreateModal(true)}>
                        {t('create-snapshot', 'Create Snapshot')}
                    </Button>
                </Can>
                <Can action={'config-revision.read'}>
                    <Button size={'small'} isSecondary onClick={() => setShowCompareSelector(!showCompareSelector)}>
                        {t('compare', 'Compare Revisions')}
                    </Button>
                    <Button size={'small'} isSecondary onClick={() => setShowWatchPatterns(!showWatchPatterns)}>
                        {t('watch-patterns', 'Watch Patterns')}
                    </Button>
                </Can>
            </div>

            {showCreateModal && (
                <CreateSnapshotModal onCreated={handleCreateSnapshot} onDismiss={() => setShowCreateModal(false)} />
            )}

            {showCompareSelector && (
                <CompareSelector
                    revisions={revisions}
                    onCompare={handleCompare}
                    onDismiss={() => setShowCompareSelector(false)}
                />
            )}

            {showWatchPatterns && <WatchPatternsManager uuid={uuid} onDismiss={() => setShowWatchPatterns(false)} />}

            {presets.length > 0 && (
                <PresetManager presets={presets} onActivate={handleActivatePreset} onDelete={handleDeletePreset} />
            )}

            {diff && selectedRevision && (
                <Card css={tw`mb-4`}>
                    <div css={tw`flex items-center justify-between mb-3`}>
                        <h3 css={tw`text-sm font-semibold text-gray-200`}>
                            Diff: {selectedRevision.hash.substring(0, 8)} vs{' '}
                            {typeof diff.revision_to === 'string' ? diff.revision_to : `#${diff.revision_to}`}
                        </h3>
                        <button
                            onClick={() => {
                                setDiff(null);
                                setSelectedRevision(null);
                            }}
                            css={tw`text-gray-400 hover:text-gray-200 text-sm`}
                        >
                            Close
                        </button>
                    </div>
                    <DiffViewer diff={diff} loading={diffLoading} />
                </Card>
            )}

            {loading ? (
                <Spinner size={'large'} centered />
            ) : revisions.length === 0 ? (
                <Card>
                    <p css={tw`text-center text-sm text-gray-400 py-4`}>
                        {t('no-revisions', 'No config revisions yet. Edit a config file to create the first snapshot.')}
                    </p>
                </Card>
            ) : (
                <>
                    {revisions.map((revision) => (
                        <RevisionRow
                            key={revision.id}
                            revision={revision}
                            isSelected={selectedRevision?.id === revision.id}
                            onViewDiff={handleViewDiff}
                            onRevert={handleRevert}
                            onPromote={handlePromote}
                        />
                    ))}

                    {totalPages > 1 && (
                        <div css={tw`flex justify-center gap-2 mt-4`}>
                            <Button
                                size={'xsmall'}
                                isSecondary
                                disabled={page <= 1}
                                onClick={() => setPage((p) => p - 1)}
                            >
                                Previous
                            </Button>
                            <span css={tw`text-sm text-gray-400 py-1`}>
                                Page {page} of {totalPages}
                            </span>
                            <Button
                                size={'xsmall'}
                                isSecondary
                                disabled={page >= totalPages}
                                onClick={() => setPage((p) => p + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    )}
                </>
            )}
        </ServerContentBlock>
    );
};

export default ConfigRevisionsContainer;
