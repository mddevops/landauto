export type BlockState = Record<string, unknown>;

export type BlockRendererProps = {
    state: BlockState;
};

export type RepeaterItem = BlockState & { id: string };

export function text(state: BlockState, key: string): string | null {
    const value = state[key];

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}

export function flag(
    state: BlockState,
    key: string,
    fallback: boolean,
): boolean {
    const value = state[key];

    return typeof value === 'boolean' ? value : fallback;
}

export function group(state: BlockState, key: string): BlockState {
    const value = state[key];

    return isObject(value) ? value : {};
}

export function items(state: BlockState, key: string): RepeaterItem[] {
    const value = state[key];

    if (!Array.isArray(value)) {
        return [];
    }

    return value.filter(
        (item): item is RepeaterItem =>
            isObject(item) && typeof item.id === 'string',
    );
}

function isObject(value: unknown): value is BlockState {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
