import type { BlockState } from '@/blocks/state';

export type DesignerSite = {
    public_id: string;
    name: string;
};

export type DesignerPage = {
    public_id: string;
    title: string;
    slug: string;
    is_home: boolean;
};

export type DesignerBlock = {
    public_id: string;
    slug: string;
    name: string;
    version: string;
    state: BlockState;
};
