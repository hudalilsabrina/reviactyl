import { useState } from 'react';
import tw from 'twin.macro';
import Button from '@/reviactyl/elements/Button';
import Card from '@/reviactyl/ui/Card';

interface Props {
    onCreated: (message?: string, files?: string[]) => Promise<void>;
    onDismiss: () => void;
}

const CreateSnapshotModal = ({ onCreated, onDismiss }: Props) => {
    const [message, setMessage] = useState('');
    const [filesInput, setFilesInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async () => {
        setLoading(true);
        setError('');

        try {
            const files = filesInput
                .split('\n')
                .map((f) => f.trim())
                .filter(Boolean);

            await onCreated(message || undefined, files.length > 0 ? files : undefined);
        } catch (err: any) {
            setError(err?.response?.data?.error || 'Failed to create snapshot.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card css={tw`mb-4`}>
            <h3 css={tw`text-sm font-semibold text-gray-200 mb-3`}>Create Manual Snapshot</h3>

            <div css={tw`space-y-3`}>
                <div>
                    <label css={tw`block text-xs text-gray-400 mb-1`}>Message (optional)</label>
                    <input
                        type='text'
                        value={message}
                        onChange={(e) => setMessage(e.target.value)}
                        placeholder='e.g. Before upgrading to Paper 1.21'
                        css={tw`w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200`}
                    />
                </div>

                <div>
                    <label css={tw`block text-xs text-gray-400 mb-1`}>
                        Specific files (optional, one per line — leave empty to snapshot all tracked files)
                    </label>
                    <textarea
                        value={filesInput}
                        onChange={(e) => setFilesInput(e.target.value)}
                        placeholder={'server.properties\nbukkit.yml'}
                        rows={3}
                        css={tw`w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 font-mono`}
                    />
                </div>

                {error && <p css={tw`text-xs text-red-400`}>{error}</p>}

                <div css={tw`flex gap-2 justify-end`}>
                    <Button size={'xsmall'} isSecondary onClick={onDismiss}>
                        Cancel
                    </Button>
                    <Button size={'xsmall'} color={'green'} isLoading={loading} onClick={handleSubmit}>
                        Create Snapshot
                    </Button>
                </div>
            </div>
        </Card>
    );
};

export default CreateSnapshotModal;
