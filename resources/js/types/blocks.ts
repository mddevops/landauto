export type BlockOwnerScope = 'platform' | 'developer' | 'workspace_private';

export type AuthoringBlock = {
    public_id: string;
    name: string;
    slug: string;
    versions_count: number;
    updated_at: string | null;
};

export type AuthoringBlockDetail = AuthoringBlock & {
    owner_scope: BlockOwnerScope;
    owner_scope_label: string;
    owner_name: string;
    created_at: string | null;
};
