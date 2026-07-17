import tw from 'twin.macro';
import Card from '@/reviactyl/ui/Card';
import Button from '@/reviactyl/elements/Button';
import { ConfigRevision } from '@/api/server/configRevisions';

interface Props {
    presets: ConfigRevision[];
    onActivate: (presetName: string) => void;
    onDelete: (presetName: string) => void;
}

const PresetManager = ({ presets, onActivate, onDelete }: Props) => {
    return (
        <Card css={tw`mb-4`}>
            <h3 css={tw`text-sm font-semibold text-gray-200 mb-3`}>Config Presets</h3>
            <div css={tw`space-y-2`}>
                {presets.map((preset) => (
                    <div key={preset.id} css={tw`flex items-center justify-between py-2 px-3 bg-gray-800/50 rounded`}>
                        <div>
                            <span css={tw`text-sm text-primary-300 font-medium`}>{preset.preset_name}</span>
                            <span css={tw`text-xs text-gray-500 ml-2`}>
                                {preset.file_count} files &middot;{' '}
                                {preset.created_at ? new Date(preset.created_at).toLocaleDateString() : 'Unknown'}
                            </span>
                        </div>
                        <div css={tw`flex gap-2`}>
                            <Button
                                size={'xsmall'}
                                color={'green'}
                                onClick={() => preset.preset_name && onActivate(preset.preset_name)}
                            >
                                Activate
                            </Button>
                            <Button
                                size={'xsmall'}
                                isSecondary
                                color={'red'}
                                onClick={() => preset.preset_name && onDelete(preset.preset_name)}
                            >
                                Remove
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </Card>
    );
};

export default PresetManager;
