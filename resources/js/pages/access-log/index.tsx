import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/AccessLogController';
import ExportMenu from '@/components/export-menu';
import Heading from '@/components/heading';
import { Hint } from '@/components/hint';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

type Entry = {
    id: number;
    at: string;
    kind: string;
    user: string | null;
    email: string | null;
    method: string | null;
    route: string | null;
    path: string | null;
    status: number | null;
    ip: string | null;
    user_agent: string | null;
};

type Props = {
    entries: {
        data: Entry[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        user: number | null;
        ip: string;
        kind: string | null;
        from: string | null;
        to: string | null;
    };
    users: { id: number; name: string }[];
    retention_days: number;
};

export default function AccessLogIndex({
    entries,
    filters,
    users,
    retention_days: retentionDays,
}: Props) {
    const { t, locale } = useTranslation();
    const [ip, setIp] = useState(filters.ip);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    setLayoutProps({
        breadcrumbs: [{ title: t('accesslog.title'), href: index() }],
    });

    const apply = (patch: Partial<Record<string, string>> = {}): void => {
        const next = {
            user: filters.user ? String(filters.user) : '',
            kind: filters.kind ?? '',
            ip,
            from,
            to,
            ...patch,
        };

        router.get(
            index.url(),
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, replace: true },
        );
    };

    const dateTime = new Intl.DateTimeFormat(locale, {
        dateStyle: 'short',
        timeStyle: 'medium',
    });

    return (
        <>
            <Head title={t('accesslog.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('accesslog.title')}
                        description={t('accesslog.description', {
                            days: retentionDays,
                        })}
                    />
                    <ExportMenu
                        view="access"
                        query={{ ...filters, user: filters.user ?? '' }}
                    />
                </div>

                <form
                    className="grid items-end gap-3 sm:grid-cols-[14rem_14rem_9rem_9rem_17rem]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <SelectField
                        id="access-user"
                        searchable
                        name="user"
                        defaultValue={filters.user ? String(filters.user) : ''}
                        onChange={(value) => apply({ user: value })}
                        options={[
                            {
                                value: '',
                                label: t('accesslog.filters.all_users'),
                            },
                            ...users.map((user) => ({
                                value: String(user.id),
                                label: user.name,
                            })),
                        ]}
                    />
                    <SelectField
                        id="access-kind"
                        name="kind"
                        defaultValue={filters.kind ?? ''}
                        onChange={(value) => apply({ kind: value })}
                        options={[
                            {
                                value: '',
                                label: t('accesslog.filters.all_kinds'),
                            },
                            ...['page', 'login_failed', 'lockout'].map(
                                (kind) => ({
                                    value: kind,
                                    label: t(`accesslog.kinds.${kind}`),
                                }),
                            ),
                        ]}
                    />
                    <Input
                        type="date"
                        value={from}
                        aria-label={t('accesslog.filters.from')}
                        onChange={(event) => {
                            setFrom(event.target.value);
                            apply({ from: event.target.value });
                        }}
                    />
                    <Input
                        type="date"
                        value={to}
                        aria-label={t('accesslog.filters.to')}
                        onChange={(event) => {
                            setTo(event.target.value);
                            apply({ to: event.target.value });
                        }}
                    />
                    <div className="flex gap-2">
                        <Input
                            value={ip}
                            onChange={(event) => setIp(event.target.value)}
                            placeholder={t('accesslog.filters.ip')}
                            aria-label={t('accesslog.filters.ip')}
                        />
                        <Button type="submit" variant="secondary">
                            {t('accesslog.filters.apply')}
                        </Button>
                    </div>
                </form>

                {entries.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('accesslog.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    {[
                                        'when',
                                        'who',
                                        'page',
                                        'status',
                                        'ip',
                                        'browser',
                                    ].map((column) => (
                                        <th
                                            key={column}
                                            className="px-4 py-2 font-medium"
                                        >
                                            {t(`accesslog.columns.${column}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {entries.data.map((entry) => (
                                    <tr
                                        key={entry.id}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            {dateTime.format(
                                                new Date(entry.at),
                                            )}
                                        </td>
                                        <td className="px-4 py-2">
                                            {entry.user ?? entry.email ?? '—'}
                                            {entry.kind !== 'page' && (
                                                <div className="text-xs font-medium text-red-700 dark:text-red-400">
                                                    {t(
                                                        `accesslog.kinds.${entry.kind}`,
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 break-all">
                                            <div className="font-mono text-xs">
                                                {entry.method} {entry.path}
                                            </div>
                                            {entry.route && (
                                                <div className="text-xs text-muted-foreground">
                                                    {entry.route}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-2">
                                            {entry.status ?? ''}
                                        </td>
                                        <td className="px-4 py-2 font-mono text-xs whitespace-nowrap">
                                            {entry.ip}
                                        </td>
                                        <td className="max-w-xs px-4 py-2 text-xs text-muted-foreground">
                                            {entry.user_agent && (
                                                <Hint label={entry.user_agent}>
                                                    <span
                                                        tabIndex={0}
                                                        className="block truncate focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                    >
                                                        {entry.user_agent}
                                                    </span>
                                                </Hint>
                                            )}
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
