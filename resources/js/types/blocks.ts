export type BlockOwnerScope = 'platform' | 'developer' | 'workspace_private';

export type AuthoringBlock = {
    public_id: string;
    name: string;
    slug: string;
    category: string;
    category_label: string;
    versions_count: number;
    updated_at: string | null;
};

export type AuthoringBlockDetail = AuthoringBlock & {
    owner_scope: BlockOwnerScope;
    owner_scope_label: string;
    owner_name: string;
    created_at: string | null;
};

export type DraftSourceKey = 'html' | 'css' | 'js' | 'schema';

export type DraftSources = Record<DraftSourceKey, string>;

/** An automated publishing check failure (ADR-008 §7) with its file and line or schema path. */
export type BlockCheckIssue = {
    source: DraftSourceKey;
    line: number | null;
    path: string | null;
    message: string;
};

export type PublishedBlockVersion = {
    version: string;
    runtime_label: string;
    published_at: string | null;
};

/** Public access card of a catalog item (D-121); mode values come from the backend enum. */
export type CatalogAccessCard = {
    mode: string;
    restricted: boolean;
    label: string;
    detail: string | null;
};

/** Catalog access settings of a Block or Template; prices are human decimal strings, empty when not offered. */
export type BlockAccessSettings = {
    mode: string;
    site_price: string;
    workspace_price: string;
};

export type BlockDraft = {
    revision: number;
    sources: DraftSources;
    preview: Record<string, unknown>;
    checks: BlockCheckIssue[];
    saved_at: string | null;
};
