import { Head, setLayoutProps } from '@inertiajs/react';
import {
    download,
    index,
} from '@/actions/App/Http/Controllers/AuditLogController';
import Heading from '@/components/heading';
import { Hint } from '@/components/hint';
import ExportMenu from '@/components/export-menu';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Entry = {
    id: number;
    action: string;
    user: string | null;
    ip: string | null;
    subject: string;
    subject_kind: string;
    subject_id: number;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    at: string | null;
};

type Archive = {
    month: string;
    count: number;
    created_at: string;
    file_sha256: string;
    purged_at: string | null;
};

type Paginated = {
    data: Entry[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/** The recorded values of an entry as small key and value chips instead of raw JSON. */
function Facts({ data }: { data: Record<string, unknown> }) {
    return (
        <ul className="flex flex-wrap gap-1">
            {Object.entries(data).map(([key, value]) => (
                <li
                    key={key}
                    className="rounded bg-muted px-1.5 py-0.5 text-xs"
                >
                    <span className="text-muted-foreground">{key}</span>{' '}
                    <span className="font-medium break-all">
                        {typeof value === 'object' && value !== null
                            ? JSON.stringify(value)
                            : String(value)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export default function AuditIndex({
    entries,
    archives,
}: {
    entries: Paginated;
    archives: Archive[];
}) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('orders.audit.title'), href: index() }],
    });

    // A readable sentence for each recorded action, the raw code when there is none.
    const named = (key: string, fallback: string): string => {
        const text = t(key);

        return text === key ? fallback : text;
    };

    const dateTime = new Intl.DateTimeFormat(locale, {
        dateStyle: 'short',
        timeStyle: 'short',
    });

    return (
        <>
            <Head title={t('orders.audit.head')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={t('orders.audit.title')}
                        description={t('orders.audit.description')}
                    />
                    <ExportMenu view="audit" />
                </div>

                <section className="space-y-2">
                    <h2 className="text-base font-semibold">
                        {t('orders.audit.archives.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('orders.audit.archives.help')}
                    </p>
                    {archives.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('orders.audit.archives.empty')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                            {archives.map((archive) => (
                                <li
                                    key={archive.month}
                                    className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {archive.month}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' · '}
                                            {archive.count}{' '}
                                            {t('orders.audit.archives.entries')}
                                            {' · '}
                                            {t(
                                                'orders.audit.archives.created',
                                            )}{' '}
                                            {archive.created_at.slice(0, 10)}
                                            {' · '}
                                            <Hint label={archive.file_sha256}>
                                                <span
                                                    tabIndex={0}
                                                    className="font-mono text-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                >
                                                    {archive.file_sha256.slice(
                                                        0,
                                                        12,
                                                    )}
                                                </span>
                                            </Hint>
                                        </span>
                                    </span>
                                    {archive.purged_at ? (
                                        <span className="text-xs text-muted-foreground">
                                            {t('orders.audit.archives.purged', {
                                                date: archive.purged_at.slice(
                                                    0,
                                                    10,
                                                ),
                                            })}
                                        </span>
                                    ) : (
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <a
                                                href={download.url(
                                                    archive.month,
                                                )}
                                            >
                                                {t(
                                                    'orders.audit.archives.download',
                                                )}
                                            </a>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {entries.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.audit.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('orders.audit.when')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('orders.audit.who')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('orders.audit.action')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('orders.audit.subject')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('orders.audit.details')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.data.map((entry) => (
                                    <tr
                                        key={entry.id}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            {entry.at
                                                ? dateTime.format(
                                                      new Date(entry.at),
                                                  )
                                                : ''}
                                        </td>
                                        <td className="px-4 py-2">
                                            {entry.user ??
                                                t('orders.audit.system')}
                                            {entry.ip && (
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {entry.ip}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-2">
                                            {named(
                                                `orders.audit.actions.${entry.action}`,
                                                entry.action,
                                            )}
                                        </td>
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            {named(
                                                `orders.audit.subjects.${entry.subject_kind}`,
                                                entry.subject_kind,
                                            )}{' '}
                                            #{entry.subject_id}
                                        </td>
                                        <td className="px-4 py-2">
                                            <div className="space-y-1">
                                                {entry.before && (
                                                    <Facts
                                                        data={entry.before}
                                                    />
                                                )}
                                                {entry.before &&
                                                    entry.after && (
                                                        <div className="text-xs text-muted-foreground">
                                                            ↓
                                                        </div>
                                                    )}
                                                {entry.after && (
                                                    <Facts data={entry.after} />
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={entries} />
            </div>
        </>
    );
}
