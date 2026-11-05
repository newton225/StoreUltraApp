import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/OrderController';
import { download } from '@/actions/App/Http/Controllers/DocumentController';
import { show as archive } from '@/actions/App/Http/Controllers/OrderDocumentArchiveController';
import GuidedTour, { type TourStep } from '@/components/guided-tour';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import OrderAdvancePayments from '@/components/order-advance-payments';
import OrderDocumentsZip from '@/components/order-documents-zip';
import OrderProformaBatch from '@/components/order-proforma-batch';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    proforma_document: number | null;
    invoice_document: number | null;
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
    proformaZones: { id: number; name: string; count: number }[];
    archiveZones: { id: number; name: string }[];
    collectZones: {
        id: number;
        name: string;
        orders: {
            id: number;
            client: string;
            due: number;
            paid: number;
            outstanding: number;
        }[];
    }[];
    paymentMethods: {
        id: number;
        name: string;
        requires_reference: boolean;
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
    proformaZones,
    archiveZones,
    collectZones,
    paymentMethods,
}: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [search, setSearch] = useState(filters.search);
    const [week, setWeek] = useState(filters.week ?? '');
    const [selected, setSelected] = useState<number[]>([]);

    // Only the orders shown on this page count: a page change leaves the others out of the download.
    const chosen = orders.data.filter((order) => selected.includes(order.id));
    const withProforma = chosen.filter((order) => order.proforma_document);
    const withInvoice = chosen.filter((order) => order.invoice_document);
    const allChosen =
        orders.data.length > 0 && chosen.length === orders.data.length;

    const toggle = (id: number, on: boolean): void =>
        setSelected((current) =>
            on ? [...current, id] : current.filter((item) => item !== id),
        );

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
                            <Button variant="info" asChild data-tour="new">
                                <Link href={create()}>{t('orders.new')}</Link>
                            </Button>
                        )}
                    </div>
                </div>

                {(proformaZones.length > 0 || collectZones.length > 0) && (
                    <div className="space-y-3" data-tour="batches">
                        {proformaZones.length > 0 && (
                            <OrderProformaBatch zones={proformaZones} />
                        )}
                        {collectZones.length > 0 && (
                            <OrderAdvancePayments
                                zones={collectZones}
                                methods={paymentMethods}
                            />
                        )}
                    </div>
                )}

                <OrderDocumentsZip zones={archiveZones} week={filters.week} />

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

                {chosen.length > 0 && (
                    <div
                        role="region"
                        aria-label={t('orders.archive.selected', {
                            count: chosen.length,
                        })}
                        className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm fade-in"
                    >
                        <span className="font-medium">
                            {t('orders.archive.selected', {
                                count: chosen.length,
                            })}
                        </span>
                        <div className="flex flex-wrap items-center gap-2">
                            {(
                                [
                                    ['proforma', withProforma],
                                    ['invoice', withInvoice],
                                ] as const
                            ).map(([type, list]) =>
                                list.length > 0 ? (
                                    <Button
                                        key={type}
                                        asChild
                                        size="sm"
                                        variant="info"
                                    >
                                        <a
                                            href={archive.url({
                                                query: {
                                                    type,
                                                    orders: list.map(
                                                        (order) => order.id,
                                                    ),
                                                },
                                            })}
                                        >
                                            {t(
                                                `orders.archive.download_${type}s`,
                                                { count: list.length },
                                            )}
                                        </a>
                                    </Button>
                                ) : null,
                            )}
                            {withProforma.length === 0 &&
                                withInvoice.length === 0 && (
                                    <span className="text-muted-foreground">
                                        {t('orders.archive.nothing')}
                                    </span>
                                )}
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setSelected([])}
                            >
                                {t('orders.archive.clear')}
                            </Button>
                        </div>
                    </div>
                )}

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
                                    <th className="w-10 px-4 py-2">
                                        <Checkbox
                                            checked={allChosen}
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked === true
                                                        ? orders.data.map(
                                                              (order) =>
                                                                  order.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label={t(
                                                'orders.archive.select_all',
                                            )}
                                        />
                                    </th>
                                    <SortHeader
                                        column="reference"
                                        state={filters}
                                    >
                                        {t('orders.columns.reference')}
                                    </SortHeader>
                                    <SortHeader column="client" state={filters}>
                                        {t('orders.columns.client')}
                                    </SortHeader>
                                    <SortHeader
                                        column="week"
                                        state={filters}
                                        className="whitespace-nowrap"
                                    >
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
                                    <th className="px-4 py-2">
                                        {t('orders.archive.column')}
                                    </th>
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
                                            <Checkbox
                                                checked={selected.includes(
                                                    order.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggle(
                                                        order.id,
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={t(
                                                    'orders.archive.select_row',
                                                    {
                                                        reference:
                                                            order.reference ??
                                                            '',
                                                    },
                                                )}
                                            />
                                        </td>
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            {order.reference}
                                        </td>
                                        <td className="px-4 py-2 font-medium">
                                            {order.client?.name}
                                        </td>
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            {date(order.delivery_week)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {order.kits_count}
                                        </td>
                                        <td className="px-4 py-2 text-right whitespace-nowrap">
                                            {money(order.total_sale)}
                                        </td>
                                        <td className="px-4 py-2">
                                            <StatusBadge
                                                group="orders"
                                                status={order.status}
                                            />
                                        </td>
                                        <td className="px-4 py-2">
                                            <div className="flex flex-wrap gap-1">
                                                {(
                                                    [
                                                        [
                                                            'proforma',
                                                            order.proforma_document,
                                                        ],
                                                        [
                                                            'invoice',
                                                            order.invoice_document,
                                                        ],
                                                    ] as const
                                                ).map(([type, id]) =>
                                                    id === null ? null : (
                                                        <Button
                                                            key={type}
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <a
                                                                href={download.url(
                                                                    id,
                                                                )}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                aria-label={t(
                                                                    `orders.archive.open_${type}`,
                                                                    {
                                                                        client:
                                                                            order
                                                                                .client
                                                                                ?.name ??
                                                                            '',
                                                                    },
                                                                )}
                                                            >
                                                                <FileText
                                                                    className="text-info-text"
                                                                    aria-hidden
                                                                />
                                                                {t(
                                                                    `orders.archive.pdf_${type}`,
                                                                )}
                                                            </a>
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
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
