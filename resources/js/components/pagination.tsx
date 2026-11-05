import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

export type PageLinks = {
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total?: number;
};

/**
 * The pages to show: the first, the last, and the one before, the current and the one after, with a gap mark
 * where pages are left out (a gap of a single page shows that page instead).
 */
function windowOf(current: number, last: number): (number | 'gap')[] {
    const wanted = [...new Set([1, current - 1, current, current + 1, last])]
        .filter((number) => number >= 1 && number <= last)
        .sort((a, b) => a - b);
    const items: (number | 'gap')[] = [];

    wanted.forEach((number, index) => {
        const before = wanted[index - 1];

        if (before !== undefined && number - before === 2) {
            items.push(before + 1);
        } else if (before !== undefined && number - before > 2) {
            items.push('gap');
        }

        items.push(number);
    });

    return items;
}

/**
 * The pager under every list paginated on the server, as defined in the playbook (UI-47): "Page X of Y" on the
 * left; on the right First, Previous, the page numbers, Next and Last. The current page is framed and marked
 * for assistive technology; an unavailable step is dimmed. Nothing is drawn when everything fits on one page.
 * The links keep the filters of the address; `pageName` is the page parameter when a page holds several
 * paginated lists (the default is `page`).
 */
export default function Pagination({
    page,
    pageName = 'page',
}: {
    page: PageLinks;
    pageName?: string;
}) {
    const { t } = useTranslation();
    const { url } = usePage();

    if (page.last_page <= 1) {
        return null;
    }

    const hrefOf = (number: number): string => {
        const target = new URL(url, 'http://localhost');
        target.searchParams.set(pageName, String(number));

        return `${target.pathname}${target.search}`;
    };

    const step = (number: number, label: string, enabled: boolean) =>
        enabled ? (
            <Button
                asChild
                variant="ghost"
                className="h-8 px-2.5 pointer-coarse:h-11 pointer-coarse:px-3"
            >
                <Link href={hrefOf(number)} preserveScroll>
                    {label}
                </Link>
            </Button>
        ) : (
            <Button
                variant="ghost"
                className="h-8 px-2.5 pointer-coarse:h-11 pointer-coarse:px-3"
                disabled
            >
                {label}
            </Button>
        );

    const onFirst = page.current_page <= 1;
    const onLast = page.current_page >= page.last_page;

    return (
        <nav
            className="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 rounded-lg border border-border px-3 py-1.5 text-sm"
            aria-label={t('common.pagination.label')}
        >
            <span className="text-muted-foreground">
                {t('common.pagination.page', {
                    current: page.current_page,
                    last: page.last_page,
                })}
            </span>
            <div className="flex flex-wrap items-center">
                {step(1, t('common.pagination.first'), !onFirst)}
                {step(
                    page.current_page - 1,
                    t('common.pagination.previous'),
                    !onFirst,
                )}
                {windowOf(page.current_page, page.last_page).map(
                    (item, index) =>
                        item === 'gap' ? (
                            <span
                                key={`gap-${index}`}
                                className="px-2 text-muted-foreground"
                                aria-hidden
                            >
                                …
                            </span>
                        ) : item === page.current_page ? (
                            <Button
                                key={item}
                                asChild
                                variant="outline"
                                className="h-8 min-w-8 px-2.5 pointer-coarse:h-11 pointer-coarse:min-w-11"
                            >
                                <span
                                    aria-current="page"
                                    aria-label={t('common.pagination.go_to', {
                                        page: item,
                                    })}
                                >
                                    {item}
                                </span>
                            </Button>
                        ) : (
                            <Button
                                key={item}
                                asChild
                                variant="ghost"
                                className="h-8 min-w-8 px-2.5 pointer-coarse:h-11 pointer-coarse:min-w-11"
                            >
                                <Link
                                    href={hrefOf(item)}
                                    preserveScroll
                                    aria-label={t('common.pagination.go_to', {
                                        page: item,
                                    })}
                                >
                                    {item}
                                </Link>
                            </Button>
                        ),
                )}
                {step(
                    page.current_page + 1,
                    t('common.pagination.next'),
                    !onLast,
                )}
                {step(page.last_page, t('common.pagination.last'), !onLast)}
            </div>
        </nav>
    );
}
