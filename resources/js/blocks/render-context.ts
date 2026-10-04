import { createContext, use } from 'react';
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
};

export const BlockRenderContext = createContext<BlockRenderContextValue>({
    assetUrl: () => null,
    pageHref: () => null,
    vehicle: () => null,
    vehicles: [],
    hasPopup: () => false,
    openPopup: null,
});

export function useBlockRenderContext(): BlockRenderContextValue {
    return use(BlockRenderContext);
}
