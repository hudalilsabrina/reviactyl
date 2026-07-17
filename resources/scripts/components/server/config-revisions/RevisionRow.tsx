import { useState } from 'react';
import Card from '@/reviactyl/ui/Card';
import tw from 'twin.macro';
import Button from '@/reviactyl/elements/Button';
import Can from '@/reviactyl/elements/Can';
import { ConfigRevision } from '@/api/server/configRevisions';

interface Props {
    revision: ConfigRevision;
    isSelected: boolean;
    onViewDiff: (revision: ConfigRevision) => void;
    onRevert: (revision: ConfigRevision) => void;
    onPromote: (revision: ConfigRevision, name: string) => void;
}

const RevisionRow = ({ revision, isSelected, onViewDiff, onRevert, onPromote }: Props) => {
    const [showPromoteInput, setShowPromoteInput] = useState(false);
    const [presetName, setPresetName] = useState('');

    const handlePromote = () => {
        if (presetName.trim()) {
            onPromote(revision, presetName.trim());
            setShowPromoteInput(false);
            setPresetName('');
        }
    };

    return (
        <Card css={[tw`mb-2`, isSelected && tw`border-primary-500`]}>
            <div css={tw`flex items-start justify-between`}>
                <div css={tw`flex-1 min-w-0`}>
                    <div css={tw`flex items-center gap-2 mb-1`}>
                        <code css={tw`font-mono text-xs bg-gray-800 rounded px-1.5 py-0.5 text-gray-300`}>
                            {revision.hash?.substring(0, 8) ?? '—'}
                        </code>
                        {revision.is_preset && (
                            <span css={tw`text-xs bg-primary-500/20 text-primary-300 rounded px-1.5 py-0.5`}>
                                preset: {revision.preset_name}
                            </span>
                        )}
                        <span css={tw`text-xs text-gray-500`}>
                            {revision.file_count} file{revision.file_count !== 1 ? 's' : ''}
                        </span>
                    </div>

                    <p css={tw`text-sm text-gray-200 mb-1`}>{revision.message}</p>

                    <div css={tw`flex items-center gap-2 text-xs text-gray-500`}>
                        <span>{revision.author?.username ?? 'Unknown'}</span>
                        <span>&middot;</span>
                        <span>{new Date(revision.created_at).toLocaleString()}</span>
                    </div>

                    {revision.files.length > 0 && (
                        <div css={tw`mt-2 flex flex-wrap gap-1`}>
                            {revision.files.map((file) => (
                                <code
                                    key={file}
                                    css={tw`text-xs bg-gray-800/60 rounded px-1.5 py-0.5 text-gray-400 font-mono`}
                                >
                                    {file}
                                </code>
                            ))}
                        </div>
                    )}
                </div>

                <div css={tw`flex items-center gap-2 ml-4 flex-shrink-0`}>
                    <Can action={'config-revision.read'}>
                        <Button size={'xsmall'} isSecondary onClick={() => onViewDiff(revision)}>
                            Diff
                        </Button>
                    </Can>

                    <Can action={'config-revision.revert'}>
                        <Button size={'xsmall'} isSecondary color={'red'} onClick={() => onRevert(revision)}>
                            Revert
                        </Button>
                    </Can>

                    {!revision.is_preset && (
                        <Can action={'config-revision.preset'}>
                            {!showPromoteInput ? (
                                <Button
                                    size={'xsmall'}
                                    isSecondary
                                    color={'green'}
                                    onClick={() => setShowPromoteInput(true)}
                                >
                                    Save as Preset
                                </Button>
                            ) : (
                                <div css={tw`flex items-center gap-1`}>
                                    <input
                                        type='text'
                                        value={presetName}
                                        onChange={(e) => setPresetName(e.target.value)}
                                        placeholder='Preset name'
                                        css={tw`bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 w-32`}
                                        onKeyDown={(e) => e.key === 'Enter' && handlePromote()}
                                    />
                                    <Button size={'xsmall'} color={'green'} onClick={handlePromote}>
                                        Save
                                    </Button>
                                    <Button
                                        size={'xsmall'}
                                        isSecondary
                                        onClick={() => {
                                            setShowPromoteInput(false);
                                            setPresetName('');
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            )}
                        </Can>
                    )}
                </div>
            </div>
        </Card>
    );
};

export default RevisionRow;
