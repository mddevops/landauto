import type { SandboxSource } from '@/blocks/sandboxed-block';
import type { BlockSchema } from '@/blocks/schema';
import type { BlockState } from '@/blocks/state';
import type { CatalogAccessCard } from '@/types/blocks';
import type { RouteDefinition, RouteFormDefinition } from '@/wayfinder';

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
    /** Present for sandboxed Block Versions (ADR-008); null for official renderers. */
    sandbox: SandboxSource | null;
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

/** Block action URLs of one Designer: a Site Draft or a Template Draft. */
export type DesignerBlockRoutes = {
    add: (pageId: string) => string;
    state: (blockId: string) => string;
    move: (blockId: string) => string;
    duplicate: (blockId: string) => string;
    visibility: (blockId: string) => string;
    destroy: (blockId: string) => string;
};

/** Page actions of one Designer; `seo` is null where Pages have no SEO (Template Drafts). */
export type DesignerPageRoutes = {
    href: (pageId: string) => string;
    store: RouteFormDefinition<'post'>;
    update: (pageId: string) => RouteFormDefinition<'post'>;
    destroy: (pageId: string) => RouteFormDefinition<'post'>;
    seo: ((pageId: string) => RouteDefinition<'patch'>) | null;
};

/** Customer catalog item (D-121); `available` is decided by the backend for this Site. */
export type DesignerLibraryBlock = {
    slug: string;
    name: string;
    author: string | null;
    access: CatalogAccessCard;
    available: boolean;
    reason: string | null;
};
