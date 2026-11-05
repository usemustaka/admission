import { usePage } from '@inertiajs/react';
import AppHeaderLayout from '@/layouts/app/app-header-layout';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

/**
 * Staff get the sidebar layout of the admin panel, everyone else the header
 * layout of the parent portal.
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
        ? AppSidebarLayout
        : AppHeaderLayout;

    return <Layout breadcrumbs={breadcrumbs}>{children}</Layout>;
}
