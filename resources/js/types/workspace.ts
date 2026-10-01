export type WorkspaceSummary = {
    public_id: string;
    name: string;
};

export type WorkspaceContext = {
    current: WorkspaceSummary | null;
    available: WorkspaceSummary[];
};
