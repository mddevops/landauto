import type { Auth } from '@/types/auth';
import type { DeveloperContext, PlatformContext } from '@/types/platform';
import type { SiteContext, WorkspaceContext } from '@/types/workspace';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            workspace: WorkspaceContext;
            platform: PlatformContext;
            developer: DeveloperContext;
            siteContext: SiteContext | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
