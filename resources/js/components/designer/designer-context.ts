import { createContext, use } from 'react';
import type { DesignerAsset } from '@/components/designer/types';

export type DesignerContextValue = {
    siteId: string;
    assets: DesignerAsset[];
    canUpload: boolean;
    pages: { public_id: string; title: string }[];
    blocks: { public_id: string; name: string }[];
    vehicles: { public_id: string; title: string }[];
    popups: { public_id: string; name: string }[];
};

export const DesignerContext = createContext<DesignerContextValue>({
    siteId: '',
    assets: [],
    canUpload: false,
    pages: [],
    blocks: [],
    vehicles: [],
    popups: [],
});

export function useDesignerContext(): DesignerContextValue {
    return use(DesignerContext);
}
