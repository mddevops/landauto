import { createContext, use } from 'react';
import type { SectionPageRole } from '@/blocks/catalog';
import type { DesignerAsset } from '@/components/designer/types';

export type DesignerContextValue = {
    siteId: string;
    assets: DesignerAsset[];
    canUpload: boolean;
    pages: {
        public_id: string;
        title: string;
        section_role?: SectionPageRole | null;
    }[];
    blocks: { public_id: string; name: string }[];
    vehicles: { public_id: string; title: string }[];
    popups: { public_id: string; name: string }[];
    /** On a catalog card template the vehicle field follows the page. */
    pageVehicle?: boolean;
};

export const DesignerContext = createContext<DesignerContextValue>({
    siteId: '',
    assets: [],
    canUpload: false,
    pages: [],
    blocks: [],
    vehicles: [],
    popups: [],
    pageVehicle: false,
});

export function useDesignerContext(): DesignerContextValue {
    return use(DesignerContext);
}
