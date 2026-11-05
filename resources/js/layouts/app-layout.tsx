import { usePage } from '@inertiajs/react';
import AppAdminLayout from '@/layouts/app/app-admin-layout';
import AppPortalLayout from '@/layouts/app/app-portal-layout';
import type { BreadcrumbItem } from '@/types';

/**
 * Staff get the full-width admin panel, everyone else the phone-shaped
 * parent portal.
 */
export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { abilities } = usePage().props.auth;
    const Layout = abilities.includes('core::admin.access')
        ? AppAdminLayout
        : AppPortalLayout;

    return <Layout breadcrumbs={breadcrumbs}>{children}</Layout>;
}
