import { createContext, use } from 'react';
import type { VehicleBinding } from '@/blocks/vehicles';

/** Server-derived lookups that renderers need; renderers never build URLs from raw IDs themselves. */
export type BlockRenderContextValue = {
    assetUrl: (assetId: string) => string | null;
    pageHref: (pageId: string) => string | null;
    vehicle: (vehicleId: string) => VehicleBinding | null;
    vehicles: VehicleBinding[];
};

export const BlockRenderContext = createContext<BlockRenderContextValue>({
    assetUrl: () => null,
    pageHref: () => null,
    vehicle: () => null,
    vehicles: [],
});

export function useBlockRenderContext(): BlockRenderContextValue {
    return use(BlockRenderContext);
}
