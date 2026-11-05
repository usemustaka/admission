import type { Auth } from '@/types/auth';
import type { Brand } from '@/types/brand';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            brand: Brand;
            auth: Auth;
            sidebarOpen: boolean;
            portal: {
                children: {
                    id: number;
                    name: string;
                    photo_url: string | null;
                }[];
            } | null;
            [key: string]: unknown;
        };
    }
}

declare module '@tanstack/react-table' {
    // The tables in this app only add a class name to a column.
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData, TValue> {
        className?: string;
    }
}
