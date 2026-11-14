import { createContext, use } from 'react';
import type { CatalogRuntime, PageContext } from '@/blocks/catalog';
import type { TriggerContextValue } from '@/blocks/trigger-context';
import type { VehicleBinding } from '@/blocks/vehicles';

export type OpenPopup = (
    popupId: string,
    context: TriggerContextValue,
    trigger: HTMLElement,
) => void;

/** Server-derived lookups that renderers need; renderers never build URLs from raw IDs themselves. */
export type BlockRenderContextValue = {
    assetUrl: (assetId: string) => string | null;
    pageHref: (pageId: string) => string | null;
    vehicle: (vehicleId: string) => VehicleBinding | null;
    vehicles: VehicleBinding[];
    /** Whether the public ID is an active Popup of this Site. */
    hasPopup: (popupId: string) => boolean;
    /** Present only where Popups can actually open (the preview runtime). */
    openPopup: OpenPopup | null;
    /** Catalog section of the Site, when it has one. */
    catalog?: CatalogRuntime | null;
    /** Set on catalog template Pages: the mark, model or vehicle the Page shows. */
    pageContext?: PageContext | null;
};

export const BlockRenderContext = createContext<BlockRenderContextValue>({
    assetUrl: () => null,
    pageHref: () => null,
    vehicle: () => null,
    vehicles: [],
    hasPopup: () => false,
    openPopup: null,
    catalog: null,
    pageContext: null,
});

/** Card address of a vehicle while the catalog section is on. */
export function useVehicleHref(): (vehicleId: string) => string | null {
    const { catalog } = useBlockRenderContext();

    return (vehicleId) =>
        catalog?.enabled
            ? catalog.href({ role: 'item', vehicle: vehicleId })
            : null;
}

export function useBlockRenderContext(): BlockRenderContextValue {
    return use(BlockRenderContext);
}
