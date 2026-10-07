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

export type SchemaError = { path: string; message: string };

export type TemplateError = { line: number; message: string };

export type BlockDraft = {
    revision: number;
    sources: DraftSources;
    preview: Record<string, unknown>;
    schema_errors: SchemaError[];
    template_errors: TemplateError[];
    saved_at: string | null;
};
