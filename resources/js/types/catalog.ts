export type CatalogLevelKey =
    | 'marks'
    | 'models'
    | 'generations'
    | 'series'
    | 'modifications'
    | 'equipments';

export type CatalogItem = {
    public_id: string;
    name: string;
    status: boolean;
    sort_order: number;
    [field: string]: string | number | boolean | null;
};

export type CatalogLevelColumn = {
    key: CatalogLevelKey;
    label: string;
    selectionKey: string;
    parent: string | null;
    selected: string | null;
    items: CatalogItem[];
};
