import type { BlockSchema } from '@/blocks/schema';
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
    seo: {
        title: string | null;
        description: string | null;
        noindex: boolean;
    };
};

export type DesignerBlock = {
    public_id: string;
    slug: string;
    name: string;
    version: string;
    is_hidden: boolean;
    schema: BlockSchema;
    state: BlockState;
};

/** A saved reference whose target was deleted, hidden or disabled afterwards (X-018). */
export type ReferenceIssue = {
    kind: string;
    path: string;
    target: string;
    severity: 'error' | 'warning';
    message: string;
};

export type DesignerAsset = {
    public_id: string;
    name: string;
    url: string;
    width: number;
    height: number;
};

export type DesignerLibraryBlock = {
    slug: string;
    name: string;
};
