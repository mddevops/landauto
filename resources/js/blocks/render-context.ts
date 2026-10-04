import { createContext, use } from 'react';

/** Server-derived lookups that renderers need; renderers never build URLs from raw IDs themselves. */
export type BlockRenderContextValue = {
    assetUrl: (assetId: string) => string | null;
    pageHref: (pageId: string) => string | null;
};

export const BlockRenderContext = createContext<BlockRenderContextValue>({
    assetUrl: () => null,
    pageHref: () => null,
});

export function useBlockRenderContext(): BlockRenderContextValue {
    return use(BlockRenderContext);
}
