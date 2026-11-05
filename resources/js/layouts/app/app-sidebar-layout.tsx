import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useAppearanceSync } from '@/hooks/use-appearance';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    useAppearanceSync();
    const { url } = usePage();
    const pathname = url.split('?')[0];

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {/* Keyed by the path only: a new page animates in, a filter or a reload of the same page does not. */}
                <div key={pathname} className="page-enter">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
