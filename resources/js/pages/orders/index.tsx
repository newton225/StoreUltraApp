import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/OrderController';
import GuidedTour, { type TourStep } from '@/components/guided-tour';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import OrderAdvanceBatch from '@/components/order-advance-batch';
import OrderProformaBatch from '@/components/order-proforma-batch';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SortHeader, { sortQuery } from '@/components/sort-header';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { StatusBadge } from '@/components/status-badge';

type ListedOrder = {
    id: number;
    reference: string | null;
    client: { id: number; name: string } | null;
    delivery_week: string;
    status: string;
    kits_count: number;
    total_sale: number;
};

type Paginated = {
    data: ListedOrder[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    orders: Paginated;
    filters: {
        search: string;
        status: string | null;
        week: string | null;
        sort: string | null;
        direction: 'asc' | 'desc';
    };
    statuses: string[];
    canCreate: boolean;
    proformaLocalities: { name: string; count: number }[];
    advanceLocalities: {
        name: string;
        orders: { id: number; client: string; due: number; paid: number }[];
    }[];
};

const ORDERS_TOUR: TourStep[] = [
    {
        target: 'new',
        title: 'tours.orders.new.title',
        body: 'tours.orders.new.body',
    },
    {
        target: 'batches',
        title: 'tours.orders.batches.title',
        body: 'tours.orders.batches.body',
    },
    {
        target: 'filters',
        title: 'tours.orders.filters.title',
        body: 'tours.orders.filters.body',
    },
    {
        target: 'table',
        title: 'tours.orders.table.title',
        body: 'tours.orders.table.body',
    },
];

export default function OrdersIndex({
    orders,
    filters,
    statuses,
    canCreate,
    proformaLocalities,
    advanceLocalities,
}: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [search, setSearch] = useState(filters.search);
    const [week, setWeek] = useState(filters.week ?? '');

    setLayoutProps({
        breadcrumbs: [{ title: t('orders.title'), href: index() }],
    });

    const apply = (patch: Partial<Record<string, string>> = {}): void => {
        const next = {
            search,
            week,
            status: filters.status ?? '',
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
            <Head title={t('orders.head')} />
            <GuidedTour tour="orders" steps={ORDERS_TOUR} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('orders.title')}
                        description={t('orders.description')}
                    />
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <ExportMenu view="orders" query={filters} />
                        {canCreate && (
                            <Button asChild data-tour="new">
                                <Link href={create()}>{t('orders.new')}</Link>
                            </Button>
                        )}
                    </div>
                </div>

                {(proformaLocalities.length > 0 ||
                    advanceLocalities.length > 0) && (
                    <div className="space-y-3" data-tour="batches">
                        {proformaLocalities.length > 0 && (
                            <OrderProformaBatch
                                localities={proformaLocalities}
                            />
                        )}
                        {advanceLocalities.length > 0 && (
                            <OrderAdvanceBatch localities={advanceLocalities} />
                        )}
                    </div>
                )}

                <form
                    data-tour="filters"
                    className="grid items-end justify-start gap-3 sm:grid-cols-[minmax(0,22rem)_12rem_12rem]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <div className="flex gap-2">
                        <Input
                            className="flex-1"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('orders.filters.search')}
                            aria-label={t('orders.filters.search')}
                        />
                        <Button type="submit" variant="secondary">
                            {t('orders.filters.apply')}
                        </Button>
                    </div>
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            {t('orders.filters.week')}
                        </span>
                        <Input
                            type="date"
                            value={week}
                            onChange={(event) => {
                                setWeek(event.target.value);
                                apply({ week: event.target.value });
                            }}
                            aria-label={t('orders.filters.week')}
                        />
                    </label>
                    <SelectField
                        id="filter-status"
                        name="status"
                        defaultValue={filters.status ?? ''}
                        onChange={(value) => apply({ status: value })}
                        options={[
                            {
                                value: '',
                                label: t('orders.filters.all_statuses'),
                            },
                            ...statuses.map((status) => ({
                                value: status,
                                label: t(`orders.statuses.${status}`),
                            })),
                        ]}
                    />
                </form>

                {orders.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.empty')}
                    </p>
                ) : (
                    <div
                        className="overflow-x-auto rounded-lg border border-border"
                        data-tour="table"
                    >
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <SortHeader
                                        column="reference"
                                        state={filters}
                                    >
                                        {t('orders.columns.reference')}
                                    </SortHeader>
                                    <SortHeader column="client" state={filters}>
                                        {t('orders.columns.client')}
                                    </SortHeader>
                                    <SortHeader column="week" state={filters}>
                                        {t('orders.columns.week')}
                                    </SortHeader>
                                    <SortHeader
                                        column="kits"
                                        state={filters}
                                        align="right"
                                        className="text-right"
                                    >
                                        {t('orders.columns.kits')}
                                    </SortHeader>
                                    <SortHeader
                                        column="total"
                                        state={filters}
                                        align="right"
                                        className="text-right"
                                    >
                                        {t('orders.columns.total')}
                                    </SortHeader>
                                    <SortHeader column="status" state={filters}>
                                        {t('orders.columns.status')}
                                    </SortHeader>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.map((order) => (
                                    <tr
                                        key={order.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2">
                                            {order.reference}
                                        </td>
                                        <td className="px-4 py-2 font-medium">
                                            {order.client?.name}
                                        </td>
                                        <td className="px-4 py-2">
                                            {date(order.delivery_week)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {order.kits_count}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {money(order.total_sale)}
                                        </td>
                                        <td className="px-4 py-2">
                                            <StatusBadge
                                                group="orders"
                                                status={order.status}
                                                variant="outline"
                                            />
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link href={show(order.id)}>
                                                    {t('orders.open')}
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={orders} />
            </div>
        </>
    );
}
