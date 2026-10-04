import type { Auth } from '@/types/auth';
import type { PlatformContext } from '@/types/platform';
import type { WorkspaceContext } from '@/types/workspace';

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
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
