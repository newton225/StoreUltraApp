import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { show as showOrder } from '@/actions/App/Http/Controllers/OrderController';
import { index } from '@/actions/App/Http/Controllers/PaymentController';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import SortHeader, { sortQuery } from '@/components/sort-header';
import { useTranslation } from '@/hooks/use-translation';
import { useCanDo } from '@/hooks/use-can-do';

type ListedPayment = {
    id: number;
    receipt_number: string | null;
    kind: string;
    amount: number;
    method: string;
    reference: string | null;
    paid_on: string;
    collector: string | null;
    recorder: string | null;
    payer: string | null;
    order: { id: number; reference: string | null; client: string | null };
};

type Paginated = {
    data: ListedPayment[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    payments: Paginated;
    totals: { received: number; refunded: number; net: number };
    filters: {
        search: string;
        kind: string | null;
        method: number | null;
        from: string | null;
        to: string | null;
        sort: string | null;
        direction: 'asc' | 'desc';
    };
    kinds: string[];
    methods: { id: number; name: string }[];
};

export default function PaymentsIndex({
    payments,
    totals,
    filters,
    kinds,
    methods,
}: Props) {
    const canExport = useCanDo('payments.export');
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [search, setSearch] = useState(filters.search);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    setLayoutProps({
        breadcrumbs: [{ title: t('payments.title'), href: index() }],
    });

    const apply = (patch: Partial<Record<string, string>> = {}): void => {
        const next = {
            search,
            from,
            to,
            kind: filters.kind ?? '',
            method: filters.method === null ? '' : String(filters.method),
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
            <Head title={t('payments.head')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={t('payments.title')}
                        description={t('payments.description')}
                    />
                    {canExport && (
                        <ExportMenu view="payments" query={filters} />
                    )}
                </div>

                <dl className="grid gap-3 text-sm sm:grid-cols-3">
                    <div className="rounded-lg border border-border p-3">
                        <dt className="text-muted-foreground">
                            {t('payments.totals.received')}
                        </dt>
                        <dd className="text-lg font-medium">
                            {money(totals.received)}
                        </dd>
                    </div>
                    <div className="rounded-lg border border-border p-3">
                        <dt className="text-muted-foreground">
                            {t('payments.totals.refunded')}
                        </dt>
                        <dd className="text-lg font-medium">
                            {money(totals.refunded)}
                        </dd>
                    </div>
                    <div className="rounded-lg border border-border p-3">
                        <dt className="text-muted-foreground">
                            {t('payments.totals.net')}
                        </dt>
                        <dd className="text-lg font-medium">
                            {money(totals.net)}
                        </dd>
                    </div>
                </dl>

                <form
                    className="grid items-end justify-start gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,24rem)_10rem_12rem_10rem_10rem]"
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
                            placeholder={t('payments.filters.search')}
                            aria-label={t('payments.filters.search')}
                        />
                        <Button type="submit" variant="secondary">
                            {t('payments.filters.apply')}
                        </Button>
                    </div>
                    <SelectField
                        id="filter-kind"
                        name="kind"
                        defaultValue={filters.kind ?? ''}
                        onChange={(value) => apply({ kind: value })}
                        options={[
                            {
                                value: '',
                                label: t('payments.filters.all_kinds'),
                            },
                            ...kinds.map((kind) => ({
                                value: kind,
                                label: t(`payments.kinds.${kind}`),
                            })),
                        ]}
                    />
                    <SelectField
                        id="filter-method"
                        name="method"
                        defaultValue={String(filters.method ?? '')}
                        onChange={(value) => apply({ method: value })}
                        options={[
                            {
                                value: '',
                                label: t('payments.filters.all_methods'),
                            },
                            ...methods.map((method) => ({
                                value: String(method.id),
                                label: method.name,
                            })),
                        ]}
                    />
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            {t('payments.filters.from')}
                        </span>
                        <Input
                            type="date"
                            value={from}
                            onChange={(event) => {
                                setFrom(event.target.value);
                                apply({ from: event.target.value });
                            }}
                            aria-label={t('payments.filters.from')}
                        />
                    </label>
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            {t('payments.filters.to')}
                        </span>
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => {
                                setTo(event.target.value);
                                apply({ to: event.target.value });
                            }}
                            aria-label={t('payments.filters.to')}
                        />
                    </label>
                </form>

                {payments.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('payments.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <SortHeader
                                        column="receipt"
                                        state={filters}
                                    >
                                        {t('payments.columns.receipt')}
                                    </SortHeader>
                                    <SortHeader column="date" state={filters}>
                                        {t('payments.columns.date')}
                                    </SortHeader>
                                    <SortHeader column="client" state={filters}>
                                        {t('payments.columns.client')}
                                    </SortHeader>
                                    <SortHeader column="kind" state={filters}>
                                        {t('payments.columns.kind')}
                                    </SortHeader>
                                    <SortHeader column="method" state={filters}>
                                        {t('payments.columns.method')}
                                    </SortHeader>
                                    <SortHeader
                                        column="amount"
                                        state={filters}
                                        align="right"
                                        className="text-right"
                                    >
                                        {t('payments.columns.amount')}
                                    </SortHeader>
                                    <th className="px-4 py-2 font-medium">
                                        {t('payments.columns.collector')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.data.map((payment) => (
                                    <tr
                                        key={payment.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2">
                                            <Link
                                                href={showOrder(
                                                    payment.order.id,
                                                )}
                                                className="underline underline-offset-4"
                                            >
                                                {payment.receipt_number}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2">
                                            {date(payment.paid_on)}
                                        </td>
                                        <td className="px-4 py-2">
                                            {payment.order.client}
                                            {payment.payer &&
                                                payment.payer !==
                                                    payment.order.client && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {t(
                                                            'payments.panel.paid_by',
                                                            {
                                                                name: payment.payer,
                                                            },
                                                        )}
                                                    </div>
                                                )}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Badge
                                                variant={
                                                    payment.kind === 'refund'
                                                        ? 'warning'
                                                        : payment.kind ===
                                                            'balance'
                                                          ? 'success'
                                                          : 'info'
                                                }
                                            >
                                                {t(
                                                    `payments.kinds.${payment.kind}`,
                                                )}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2">
                                            {payment.method}
                                            {payment.reference &&
                                                ` (${payment.reference})`}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {payment.kind === 'refund' && '−'}
                                            {money(payment.amount)}
                                        </td>
                                        <td className="px-4 py-2">
                                            {payment.collector ?? '—'}
                                            {payment.recorder &&
                                                payment.recorder !==
                                                    payment.collector && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {t(
                                                            'payments.panel.recorded_by',
                                                            {
                                                                name: payment.recorder,
                                                            },
                                                        )}
                                                    </div>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={payments} />
            </div>
        </>
    );
}
