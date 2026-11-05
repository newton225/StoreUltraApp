import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useAppearanceSync } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    useAppearanceSync();
    const { url } = usePage();
    const pathname = url.split('?')[0];
    const [leaving, setLeaving] = useState(false);

    // Only a real move to another page dims the current one: a filter, a sort, a form or a prefetch does not.
    useEffect(() => {
        const stopStart = router.on('start', (event) => {
            const visit = event.detail.visit;

            setLeaving(
                visit.method === 'get' &&
                    !visit.prefetch &&
                    visit.url.pathname !== window.location.pathname,
            );
        });
        const stopFinish = router.on('finish', () => setLeaving(false));

        return () => {
            stopStart();
            stopFinish();
        };
    }, []);

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {/* Keyed by the path only: a new page animates in, a filter or a reload of the same page does not. */}
                <div
                    key={pathname}
                    className={cn('page-enter', leaving && 'page-leaving')}
                >
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
