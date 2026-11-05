import type { Alerts } from '@/types/alerts';
import type { Auth } from '@/types/auth';

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
            locale: string;
            supportedLocales: string[];
            translations: Record<string, Record<string, unknown>>;
            sidebarOpen: boolean;
            alerts: Alerts | null;
            toursDone: string[];
            [key: string]: unknown;
        };
    }
}
