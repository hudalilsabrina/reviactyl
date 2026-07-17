import tw from 'twin.macro';
import { ConfigDiff } from '@/api/server/configRevisions';

interface Props {
    diff: ConfigDiff;
    loading: boolean;
}

const DiffViewer = ({ diff, loading }: Props) => {
    if (loading) {
        return (
            <div css={tw`flex items-center justify-center py-8`}>
                <div css={tw`animate-pulse text-sm text-gray-400`}>Computing diff...</div>
            </div>
        );
    }

    const files = Object.entries(diff.files);

    if (files.length === 0) {
        return <p css={tw`text-center text-sm text-gray-400 py-4`}>No differences found.</p>;
    }

    return (
        <div css={tw`space-y-4`}>
            {files.map(([filePath, fileDiff]) => (
                <div key={filePath}>
                    <div css={tw`flex items-center gap-2 mb-2 px-3 py-2 bg-gray-800 rounded-t`}>
                        <span
                            css={[
                                tw`text-xs font-bold rounded px-1.5 py-0.5`,
                                fileDiff.status === 'added'
                                    ? tw`bg-green-500/20 text-green-400`
                                    : fileDiff.status === 'deleted'
                                    ? tw`bg-red-500/20 text-red-400`
                                    : tw`bg-yellow-500/20 text-yellow-400`,
                            ]}
                        >
                            {fileDiff.status === 'added' ? 'A' : fileDiff.status === 'deleted' ? 'D' : 'M'}
                        </span>
                        <code css={tw`text-xs font-mono text-gray-300`}>{filePath}</code>
                        <span css={tw`text-xs text-gray-500 ml-auto`}>
                            <span css={tw`text-green-400`}>+{fileDiff.additions}</span>{' '}
                            <span css={tw`text-red-400`}>-{fileDiff.deletions}</span>
                        </span>
                    </div>

                    <div css={tw`font-mono text-xs overflow-x-auto bg-gray-950 rounded-b border border-gray-800`}>
                        {fileDiff.hunks.map((hunk, hunkIdx) => (
                            <div key={hunkIdx}>
                                <div css={tw`bg-gray-800/50 px-3 py-1 text-gray-500 border-t border-b border-gray-800`}>
                                    @@ -{hunk.old_start},{hunk.old_lines} +{hunk.new_start},{hunk.new_lines} @@
                                </div>
                                {hunk.lines.map((line, lineIdx) => {
                                    const prefix = line[0];
                                    const content = line.substring(1);

                                    return (
                                        <div
                                            key={lineIdx}
                                            css={[
                                                tw`px-3 py-0.5`,
                                                prefix === '+'
                                                    ? tw`bg-green-500/10 text-green-300`
                                                    : prefix === '-'
                                                    ? tw`bg-red-500/10 text-red-300`
                                                    : tw`text-gray-400`,
                                            ]}
                                        >
                                            <span css={tw`select-none text-gray-600 mr-2`}>{prefix}</span>
                                            {content}
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
};

export default DiffViewer;
