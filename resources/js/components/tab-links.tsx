import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type TabLink = {
    key: string;
    label: string;
    /** Where the tab goes. Without it the tab only switches what the page shows (see `onSelect`). */
    href?: string;
    /** A small number beside the label, for what waits to be done in this tab. */
    count?: number;
};

/**
 * Tabs that switch between the sections of one page, each tab being a link (so the address changes and the
 * back button works). The current tab has a line under it and is announced to screen readers.
 */
export default function TabLinks({
    tabs,
    current,
    label,
    onSelect,
}: {
    tabs: TabLink[];
    current: string;
    label: string;
    onSelect?: (key: string) => void;
}) {
    return (
        <nav aria-label={label} className="border-b border-border">
            <ul className="-mb-px flex gap-1 overflow-x-auto">
                {tabs.map((tab) => {
                    const active = tab.key === current;

                    return (
                        <li key={tab.key}>
                            {tab.href ? (
                                <Link
                                    href={tab.href}
                                    data-test={`tab-${tab.key}`}
                                    aria-current={active ? 'page' : undefined}
                                    className={tabClass(active)}
                                >
                                    <TabLabel tab={tab} />
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    data-test={`tab-${tab.key}`}
                                    aria-current={active ? 'page' : undefined}
                                    onClick={() => onSelect?.(tab.key)}
                                    className={tabClass(active)}
                                >
                                    <TabLabel tab={tab} />
                                </button>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

function tabClass(active: boolean): string {
    return cn(
        'flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors',
        active
            ? 'border-foreground text-foreground'
            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
    );
}

function TabLabel({ tab }: { tab: TabLink }) {
    return (
        <>
            {tab.label}
            {tab.count !== undefined && tab.count > 0 && (
                <span className="rounded-full bg-destructive px-1.5 text-xs font-semibold text-white">
                    {tab.count}
                </span>
            )}
        </>
    );
}
