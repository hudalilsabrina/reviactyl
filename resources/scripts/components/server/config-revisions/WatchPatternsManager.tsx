import { useEffect, useState } from 'react';
import tw from 'twin.macro';
import Card from '@/reviactyl/ui/Card';
import Button from '@/reviactyl/elements/Button';
import { getWatchPatterns, updateWatchPatterns, resetWatchPatterns } from '@/api/server/configRevisions';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';

interface Props {
    uuid: string;
    onDismiss: () => void;
}

const WatchPatternsManager = ({ uuid, onDismiss }: Props) => {
    const { clearAndAddHttpError } = useFlash();
    const [isCustom, setIsCustom] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [patternsInput, setPatternsInput] = useState('');

    useEffect(() => {
        getWatchPatterns(uuid)
            .then((data) => {
                setIsCustom(data.is_custom);
                setPatternsInput(data.patterns.join('\n'));
            })
            .catch((error) => clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) }))
            .finally(() => setLoading(false));
    }, [uuid]);

    const handleSave = async () => {
        setSaving(true);
        try {
            const newPatterns = patternsInput
                .split('\n')
                .map((p) => p.trim())
                .filter(Boolean);
            await updateWatchPatterns(uuid, newPatterns);
            setIsCustom(true);
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        } finally {
            setSaving(false);
        }
    };

    const handleReset = async () => {
        setSaving(true);
        try {
            const data = await resetWatchPatterns(uuid);
            setIsCustom(false);
            setPatternsInput(data.patterns.join('\n'));
        } catch (error) {
            clearAndAddHttpError({ key: 'config-revisions', error: httpErrorToHuman(error) });
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <Card css={tw`mb-4`}>
                <p css={tw`text-sm text-gray-400`}>Loading watch patterns...</p>
            </Card>
        );
    }

    return (
        <Card css={tw`mb-4`}>
            <div css={tw`flex items-center justify-between mb-3`}>
                <h3 css={tw`text-sm font-semibold text-gray-200`}>
                    Watch Patterns
                    {isCustom && <span css={tw`text-xs text-primary-400 ml-2`}>custom</span>}
                </h3>
                <button onClick={onDismiss} css={tw`text-gray-400 hover:text-gray-200 text-sm`}>
                    Close
                </button>
            </div>

            <p css={tw`text-xs text-gray-400 mb-3`}>
                Glob patterns for files to track. One per line. Supports * and ** wildcards.
            </p>

            <textarea
                value={patternsInput}
                onChange={(e) => setPatternsInput(e.target.value)}
                rows={6}
                css={tw`w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 font-mono mb-3`}
            />

            <div css={tw`flex gap-2`}>
                <Button size={'xsmall'} color={'green'} isLoading={saving} onClick={handleSave}>
                    Save Patterns
                </Button>
                <Button size={'xsmall'} isSecondary isLoading={saving} onClick={handleReset}>
                    Reset to Defaults
                </Button>
            </div>
        </Card>
    );
};

export default WatchPatternsManager;
