export type AuthoringTemplate = {
    public_id: string;
    name: string;
    slug: string;
    site_types: string[];
    site_type_labels: string[];
    versions_count: number;
    latest_version: string | null;
    updated_at: string | null;
};

export type TemplateDetail = {
    public_id: string;
    name: string;
    slug: string;
    owner_scope: string;
    owner_label: string;
    site_types: string[];
};

export type PublishedTemplateVersion = {
    version: string;
    published_at: string | null;
};
