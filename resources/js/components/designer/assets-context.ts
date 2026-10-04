import { createContext, use } from 'react';
import type { DesignerAsset } from '@/components/designer/types';

export type DesignerAssetsValue = {
    siteId: string;
    assets: DesignerAsset[];
    canUpload: boolean;
};

export const DesignerAssetsContext = createContext<DesignerAssetsValue>({
    siteId: '',
    assets: [],
    canUpload: false,
});

export function useDesignerAssets(): DesignerAssetsValue {
    return use(DesignerAssetsContext);
}
