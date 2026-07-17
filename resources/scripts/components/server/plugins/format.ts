/**
 * Format a count compactly: 1234 -> 1.2K, 1500000 -> 1.5M.
 */
export const formatCount = (value: number): string => {
    if (value < 1000) return String(value);

    const units: [number, string][] = [
        [1_000_000_000, 'B'],
        [1_000_000, 'M'],
        [1_000, 'K'],
    ];

    for (const [threshold, suffix] of units) {
        if (value >= threshold) {
            const rounded = Math.floor((value / threshold) * 10) / 10;

            return `${rounded % 1 === 0 ? rounded.toFixed(0) : rounded}${suffix}`;
        }
    }

    return String(value);
};

const majorMinor = (version: string): string => version.split('.').slice(0, 2).join('.');

const compareVersions = (a: string, b: string): number => {
    const pa = a.split('.').map(Number);
    const pb = b.split('.').map(Number);
    for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
        const diff = (pa[i] ?? 0) - (pb[i] ?? 0);
        if (diff !== 0) return diff;
    }

    return 0;
};

/**
 * Collapse a sorted Minecraft version list into readable ranges:
 * ['1.19', '1.19.1', '1.19.2', '1.20', '1.21.4'] -> ['1.19 - 1.19.2', '1.20', '1.21.4']
 */
export const formatGameVersions = (versions: string[]): string[] => {
    const normalized = versions
        .map((v) => v.trim())
        .filter((v) => /^\d+\.\d+(\.\d+)?$/.test(v))
        .sort(compareVersions);

    if (normalized.length === 0) return [];

    const ranges: string[] = [];
    let start = normalized[0]!;
    let previous = normalized[0]!;

    const flush = () => {
        ranges.push(start === previous ? start : `${start} - ${previous}`);
    };

    for (const version of normalized.slice(1)) {
        if (majorMinor(version) === majorMinor(previous)) {
            previous = version;
            continue;
        }
        flush();
        start = version;
        previous = version;
    }
    flush();

    return ranges;
};
