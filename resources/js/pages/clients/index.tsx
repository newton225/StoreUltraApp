import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/ClientController';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SortHeader, { sortQuery } from '@/components/sort-header';
import { useTranslation } from '@/hooks/use-translation';
import { StatusBadge } from '@/components/status-badge';

type ListedClient = {
    id: number;
    reference: string | null;
    commercial_number: string | null;
    name: string;
    phone: string;
    status: string;
    zone: { id: number; name: string } | null;
};

type Paginated = {
    data: ListedClient[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    clients: Paginated;
    filters: {
        search: string;
        status: string | null;
        zone: number | null;
        sort: string | null;
        direction: 'asc' | 'desc';
    };
    zones: { id: number; name: string }[];
    statuses: string[];
    capacity: { count: number; cap: number; exceeded: boolean } | null;
};

export default function ClientsIndex({
    clients,
    filters,
    zones,
    statuses,
    capacity,
}: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);

    setLayoutProps({
        breadcrumbs: [{ title: t('clients.title'), href: index() }],
    });

    const apply = (patch: Partial<Record<string, string>>): void => {
        const next = {
            search,
            status: filters.status ?? '',
            zone: filters.zone === null ? '' : String(filters.zone),
            ...sortQuery(filters),
            ...patch,
        };

        router.get(
            index.url(),
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={t('clients.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('clients.title')}
                        description={t('clients.description')}
                    />
                    <div className="flex shrink-0 items-center gap-2">
                        <ExportMenu view="clients" query={filters} />
                        <Button variant="info" asChild>
                            <Link href={create()}>{t('clients.new')}</Link>
                        </Button>
                    </div>
                </div>

                {capacity && (
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            {t(
                                capacity.count <= 1
                                    ? 'clients.capacity.label_one'
                                    : 'clients.capacity.label',
                                {
                                    count: capacity.count,
                                    cap: capacity.cap,
                                },
                            )}
                        </p>
                        {capacity.exceeded && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    {t('clients.capacity.exceeded', {
                                        count: capacity.count,
                                        cap: capacity.cap,
                                    })}
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                )}

                <form
                    className="grid gap-3 sm:grid-cols-[minmax(0,26rem)_14rem_12rem]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({});
                    }}
                >
                    <div className="flex gap-2">
                        <Input
                            className="flex-1"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('clients.filters.search')}
                            aria-label={t('clients.filters.search')}
                        />
                        <Button type="submit" variant="secondary">
                            {t('clients.filters.apply')}
                        </Button>
                    </div>
                    <SelectField
                        id="filter-zone"
                        name="zone"
                        defaultValue={String(filters.zone ?? '')}
                        onChange={(value) => apply({ zone: value })}
                        options={[
                            {
                                value: '',
                                label: t('clients.filters.all_zones'),
                            },
                            ...zones.map((zone) => ({
                                value: String(zone.id),
                                label: zone.name,
                            })),
                        ]}
                    />
                    <SelectField
                        id="filter-status"
                        name="status"
                        defaultValue={filters.status ?? ''}
                        onChange={(value) => apply({ status: value })}
                        options={[
                            {
                                value: '',
                                label: t('clients.filters.all_statuses'),
                            },
                            ...statuses.map((status) => ({
                                value: status,
                                label: t(`clients.statuses.${status}`),
                            })),
                        ]}
                    />
                </form>

                {clients.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('clients.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <SortHeader
                                        column="reference"
                                        state={filters}
                                    >
                                        {t('clients.columns.reference')}
                                    </SortHeader>
                                    <SortHeader column="name" state={filters}>
                                        {t('clients.columns.name')}
                                    </SortHeader>
                                    <th className="px-4 py-2 font-medium">
                                        {t('clients.columns.phone')}
                                    </th>
                                    <SortHeader column="zone" state={filters}>
                                        {t('clients.columns.zone')}
                                    </SortHeader>
                                    <SortHeader column="status" state={filters}>
                                        {t('clients.columns.status')}
                                    </SortHeader>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {clients.data.map((client) => (
                                    <tr
                                        key={client.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2">
                                            {client.reference}
                                        </td>
                                        <td className="px-4 py-2 font-medium">
                                            {client.name}
                                        </td>
                                        <td className="px-4 py-2">
                                            <a
                                                href={`tel:${client.phone}`}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {client.phone}
                                            </a>
                                        </td>
                                        <td className="px-4 py-2">
                                            {client.zone?.name}
                                        </td>
                                        <td className="px-4 py-2">
                                            <StatusBadge
                                                group="clients"
                                                status={client.status}
                                            />
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link href={show(client.id)}>
                                                    {t('clients.open')}
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={clients} />
            </div>
        </>
    );
}
