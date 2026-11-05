import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type SortState = {
    sort: string | null;
    direction: 'asc' | 'desc';
};

/**
 * The sort a page keeps when its filters change: nothing when the list is in its natural order.
 */
export const sortQuery = (state: SortState): Record<string, string> =>
    state.sort === null ? {} : { sort: state.sort, direction: state.direction };

/**
 * A column header that sorts the list by that column (playbook UI-46). A click sorts ascending, the next one
 * descending. The choice goes in the address, so "back" and a shared link keep it, the page goes back to the
 * first one, and the other filters of the address are kept. The sorting is done by the server, on the whole
 * list; the server only accepts the columns it lists.
 */
export default function SortHeader({
    column,
    state,
    align = 'left',
    className,
    children,
}: {
    column: string;
    state: SortState;
    align?: 'left' | 'right';
    className?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const { url } = usePage();
    const current = state.sort === column;
    const next: 'asc' | 'desc' =
        current && state.direction === 'asc' ? 'desc' : 'asc';
    const Icon = !current
        ? ChevronsUpDown
        : state.direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    const sort = (): void => {
        const target = new URL(url, window.location.origin);
        target.searchParams.set('sort', column);
        target.searchParams.set('direction', next);
        target.searchParams.delete('page');

        router.get(
            target.pathname + target.search,
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <th
            className={cn('px-4 py-2 font-medium', className)}
            aria-sort={
                current
                    ? state.direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
        >
            <button
                type="button"
                onClick={sort}
                className={cn(
                    'inline-flex items-center gap-1 rounded-sm transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                    align === 'right' && 'flex-row-reverse',
                    current && 'text-foreground',
                )}
            >
                {children}
                <Icon
                    className={cn('size-3.5', !current && 'opacity-40')}
                    aria-hidden="true"
                />
                <span className="sr-only">
                    {t(
                        next === 'asc'
                            ? 'common.sort.ascending'
                            : 'common.sort.descending',
                    )}
                </span>
            </button>
        </th>
    );
}
