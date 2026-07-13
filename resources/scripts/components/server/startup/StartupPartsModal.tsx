import { useCallback, useState } from 'react';
import Modal from '@/reviactyl/elements/Modal';
import Button from '@/reviactyl/elements/Button';
import Switch from '@/reviactyl/elements/Switch';
import tw from 'twin.macro';
import { StartupPart } from '@/api/server/types';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    DragEndEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

interface Props {
    visible: boolean;
    onDismissed: () => void;
    parts: StartupPart[];
    onSave: (parts: { part_id: number; enabled: boolean }[]) => Promise<void>;
}

const SortablePartItem = ({
    part,
    enabled,
    onToggle,
}: {
    part: StartupPart;
    enabled: boolean;
    onToggle: () => void;
}) => {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: part.id,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        zIndex: isDragging ? 50 : undefined,
        opacity: isDragging ? 0.8 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            css={[
                tw`flex items-center gap-3 p-3 rounded-ui border border-gray-700 bg-gray-800`,
                isDragging && tw`shadow-lg border-reviactyl`,
            ]}
        >
            <div
                {...attributes}
                {...listeners}
                css={[tw`text-gray-500 hover:text-gray-300 p-1`, { cursor: 'grab', '&:active': { cursor: 'grabbing' } }]}
            >
                <svg css={tw`w-5 h-5`} fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                    <path strokeLinecap='round' strokeLinejoin='round' strokeWidth={2} d='M4 8h16M4 16h16' />
                </svg>
            </div>
            <div css={tw`flex-1 min-w-0`}>
                <div css={tw`flex items-center gap-2`}>
                    <span css={tw`text-sm font-medium text-gray-200`}>{part.name}</span>
                    {part.required && (
                        <span css={tw`text-xs bg-reviactyl/20 text-reviactyl px-1.5 py-0.5 rounded`}>Required</span>
                    )}
                </div>
                <p css={tw`text-xs font-mono text-gray-400 truncate`}>{part.value}</p>
                {part.description && <p css={tw`text-xs text-gray-500 mt-0.5`}>{part.description}</p>}
            </div>
            <Switch
                name={`part-${part.id}`}
                readOnly={part.required}
                defaultChecked={enabled}
                onChange={() => {
                    if (!part.required) onToggle();
                }}
            />
        </div>
    );
};

const StartupPartsModal = ({ visible, onDismissed, parts: initialParts, onSave }: Props) => {
    const [parts, setParts] = useState<StartupPart[]>(initialParts);
    const [enabledMap, setEnabledMap] = useState<Record<number, boolean>>(() =>
        Object.fromEntries(initialParts.map((p) => [p.id, p.userEnabled]))
    );
    const [saving, setSaving] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const handleDragEnd = useCallback((event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        setParts((items) => {
            const oldIndex = items.findIndex((i) => i.id === active.id);
            const newIndex = items.findIndex((i) => i.id === over.id);

            return arrayMove(items, oldIndex, newIndex);
        });
    }, []);

    const togglePart = useCallback((id: number) => {
        setEnabledMap((prev) => ({ ...prev, [id]: !prev[id] }));
    }, []);

    const handleSave = async () => {
        setSaving(true);
        try {
            const payload = parts.map((p) => ({
                part_id: p.id,
                enabled: enabledMap[p.id] ?? p.defaultEnabled,
            }));
            await onSave(payload);
            onDismissed();
        } catch {
            // error handled by parent
        } finally {
            setSaving(false);
        }
    };

    // Group parts by group_name
    const groups = parts.reduce<Record<string, StartupPart[]>>((acc, part) => {
        const group = part.groupName || '';
        if (!acc[group]) acc[group] = [];
        acc[group].push(part);
        return acc;
    }, {});

    return (
        <Modal visible={visible} onDismissed={onDismissed} size='md' showSpinnerOverlay={saving}>
            <h2 css={tw`text-2xl mb-2 text-gray-100`}>Startup Command Builder</h2>
            <p css={tw`text-sm text-gray-400 mb-4`}>
                Toggle and reorder startup parts to customize your server command.
            </p>

            <div css={tw`max-h-96 overflow-y-auto space-y-4 pr-1`}>
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                    <SortableContext items={parts.map((p) => p.id)} strategy={verticalListSortingStrategy}>
                        {Object.entries(groups).map(([groupName, groupParts]) => (
                            <div key={groupName}>
                                {groupName && (
                                    <h3 css={tw`text-xs uppercase text-gray-500 font-semibold mb-2 tracking-wider`}>
                                        {groupName}
                                    </h3>
                                )}
                                <div css={tw`space-y-2`}>
                                    {groupParts.map((part) => (
                                        <SortablePartItem
                                            key={part.id}
                                            part={part}
                                            enabled={enabledMap[part.id] ?? part.defaultEnabled}
                                            onToggle={() => togglePart(part.id)}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </SortableContext>
                </DndContext>
            </div>

            <div css={tw`mt-6 flex flex-col sm:flex-row justify-end sm:space-x-4 space-y-4 sm:space-y-0`}>
                <Button isSecondary onClick={onDismissed} css={tw`w-full sm:w-auto`}>
                    Cancel
                </Button>
                <Button onClick={handleSave} css={tw`w-full sm:w-auto`}>
                    Save
                </Button>
            </div>
        </Modal>
    );
};

export default StartupPartsModal;
