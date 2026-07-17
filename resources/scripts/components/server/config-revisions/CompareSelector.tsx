import { useState } from 'react';
import tw from 'twin.macro';
import Card from '@/reviactyl/ui/Card';
import Button from '@/reviactyl/elements/Button';
import { ConfigRevision } from '@/api/server/configRevisions';

interface Props {
    revisions: ConfigRevision[];
    onCompare: (revisionA: number, revisionB: number) => void;
    onDismiss: () => void;
}

const CompareSelector = ({ revisions, onCompare, onDismiss }: Props) => {
    const [revisionA, setRevisionA] = useState<number>(revisions[0]?.id || 0);
    const [revisionB, setRevisionB] = useState<number>(revisions[1]?.id || 0);

    return (
        <Card css={tw`mb-4`}>
            <h3 css={tw`text-sm font-semibold text-gray-200 mb-3`}>Compare Two Revisions</h3>
            <div css={tw`flex items-end gap-3`}>
                <div css={tw`flex-1`}>
                    <label css={tw`block text-xs text-gray-400 mb-1`}>From</label>
                    <select
                        value={revisionA}
                        onChange={(e) => setRevisionA(Number(e.target.value))}
                        css={tw`w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200`}
                    >
                        {revisions.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.hash.substring(0, 8)} — {r.message}
                            </option>
                        ))}
                    </select>
                </div>
                <div css={tw`flex-1`}>
                    <label css={tw`block text-xs text-gray-400 mb-1`}>To</label>
                    <select
                        value={revisionB}
                        onChange={(e) => setRevisionB(Number(e.target.value))}
                        css={tw`w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200`}
                    >
                        <option value={0}>Current (live files)</option>
                        {revisions.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.hash.substring(0, 8)} — {r.message}
                            </option>
                        ))}
                    </select>
                </div>
                <Button
                    size={'small'}
                    disabled={revisionA === revisionB || !revisionA}
                    onClick={() => onCompare(revisionA, revisionB || 0)}
                >
                    Compare
                </Button>
                <Button size={'small'} isSecondary onClick={onDismiss}>
                    Cancel
                </Button>
            </div>
        </Card>
    );
};

export default CompareSelector;
