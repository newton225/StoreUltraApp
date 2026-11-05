import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { pdf } from '@/actions/App/Http/Controllers/DashboardController';
import { show as exportShow } from '@/actions/App/Http/Controllers/ExportController';
import { index as profitsIndex } from '@/actions/App/Http/Controllers/ProfitController';
import type { WeeklyRow } from '@/components/charts/dashboard-charts';
import { DashboardCharts } from '@/components/charts/dashboard-charts';
import GuidedTour, { type TourStep } from '@/components/guided-tour';
import Heading from '@/components/heading';
import { HelpHint } from '@/components/hint';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAlertItems } from '@/hooks/use-alert-items';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import type { Alerts } from '@/types/alerts';

type Filters = {
    from: string;
    to: string;
    zone: number | null;
    kit: number | null;
    shop: number | null;
};

type ShopRow = {
    shop: string;
    purchase: number;
    sale: number;
    advance: number;
    percent: number;
    balance: number;
};

type Report = {
    indicators: {
        active_clients: number;
        orders: number;
        orders_by_status: Record<string, number>;
        kits_ordered: number;
        kits_delivered: number;
        kits_remaining: number;
        sale_total: number;
        advances: number;
        balances: number;
        refunds: number;
        receivables: number;
    };
    shops: { summary: ShopRow[]; debts: number } | null;
    weekly: WeeklyRow[];
    profit: {
        theoretical_margin: number;
        real_margin: number;
        projected_margin: number;
        gap: number;
        logistics: number;
        actual_purchase: number;
    } | null;
    alerts: Alerts;
};

type Props = {
    filters: Filters;
    report: Report;
    zones: { id: number; name: string }[];
    kits: { id: number; name: string }[];
    shopChoices: { id: number; name: string }[];
    exports: string[];
};

const DASHBOARD_TOUR: TourStep[] = [
    {
        target: 'menu',
        title: 'tours.dashboard.menu.title',
        body: 'tours.dashboard.menu.body',
    },
    {
        target: 'filters',
        title: 'tours.dashboard.filters.title',
        body: 'tours.dashboard.filters.body',
    },
    {
        target: 'alerts',
        title: 'tours.dashboard.alerts.title',
        body: 'tours.dashboard.alerts.body',
    },
    {
        target: 'indicators',
        title: 'tours.dashboard.indicators.title',
        body: 'tours.dashboard.indicators.body',
    },
    {
        target: 'charts',
        title: 'tours.dashboard.charts.title',
        body: 'tours.dashboard.charts.body',
    },
];

export default function Dashboard({
    filters,
    report,
    zones,
    kits,
    shopChoices,
    exports,
}: Props) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const { indicators, shops, profit, alerts } = report;

    setLayoutProps({
        breadcrumbs: [{ title: t('navigation.dashboard'), href: dashboard() }],
    });

    const query = (
        patch: Record<string, string> = {},
    ): Record<string, string> =>
        Object.fromEntries(
            Object.entries({
                from,
                to,
                zone: filters.zone === null ? '' : String(filters.zone),
                kit: filters.kit === null ? '' : String(filters.kit),
                shop: filters.shop === null ? '' : String(filters.shop),
                ...patch,
            }).filter(([, value]) => value),
        );

    const apply = (patch: Record<string, string> = {}): void => {
        router.get(dashboard.url(), query(patch), {
            preserveState: true,
            replace: true,
        });
    };

    const figure = (label: string, value: string | number, help?: string) => (
        <div
            key={label}
            className="min-w-[16rem] flex-1 rounded-lg border border-border p-4"
        >
            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                {label}
                {help && <HelpHint label={help} />}
            </div>
            <div className="mt-1 text-xl font-semibold">{value}</div>
        </div>
    );

    const alertItems = useAlertItems(alerts);

    const shopTotals = shops?.summary.reduce(
        (sum, row) => ({
            purchase: sum.purchase + row.purchase,
            sale: sum.sale + row.sale,
            advance: sum.advance + row.advance,
            balance: sum.balance + row.balance,
        }),
        { purchase: 0, sale: 0, advance: 0, balance: 0 },
    );

    return (
        <>
            <Head title={t('dashboard.head')} />
            <GuidedTour tour="dashboard" steps={DASHBOARD_TOUR} />

            <div className="space-y-8 p-4">
                <Heading
                    title={t('dashboard.title')}
                    description={t('dashboard.description')}
                />

                <form
                    data-tour="filters"
                    className={`grid gap-3 sm:grid-cols-2 ${shopChoices.length > 0 ? 'lg:grid-cols-5' : 'lg:grid-cols-4'}`}
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="from">
                            {t('dashboard.filters.from')}
                        </Label>
                        <Input
                            id="from"
                            type="date"
                            value={from}
                            onChange={(event) => {
                                setFrom(event.target.value);
                                apply({ from: event.target.value });
                            }}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">{t('dashboard.filters.to')}</Label>
                        <Input
                            id="to"
                            type="date"
                            value={to}
                            onChange={(event) => {
                                setTo(event.target.value);
                                apply({ to: event.target.value });
                            }}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="zone">
                            {t('dashboard.filters.zone')}
                        </Label>
                        <SelectField
                            id="zone"
                            name="zone"
                            defaultValue={String(filters.zone ?? '')}
                            onChange={(value) => apply({ zone: value })}
                            options={[
                                {
                                    value: '',
                                    label: t('dashboard.filters.all_zones'),
                                },
                                ...zones.map((zone) => ({
                                    value: String(zone.id),
                                    label: zone.name,
                                })),
                            ]}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="kit">
                            {t('dashboard.filters.kit')}
                        </Label>
                        <SelectField
                            id="kit"
                            name="kit"
                            defaultValue={String(filters.kit ?? '')}
                            onChange={(value) => apply({ kit: value })}
                            options={[
                                {
                                    value: '',
                                    label: t('dashboard.filters.all_kits'),
                                },
                                ...kits.map((kit) => ({
                                    value: String(kit.id),
                                    label: kit.name,
                                })),
                            ]}
                        />
                    </div>
                    {shopChoices.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="shop">
                                {t('dashboard.filters.shop')}
                            </Label>
                            <SelectField
                                id="shop"
                                name="shop"
                                defaultValue={String(filters.shop ?? '')}
                                onChange={(value) => apply({ shop: value })}
                                options={[
                                    {
                                        value: '',
                                        label: t('dashboard.filters.all_shops'),
                                    },
                                    ...shopChoices.map((shop) => ({
                                        value: String(shop.id),
                                        label: shop.name,
                                    })),
                                ]}
                            />
                        </div>
                    )}
                </form>

                <section className="space-y-2" data-tour="alerts">
                    <h2 className="text-base font-semibold">
                        {t('dashboard.alerts.title')}
                    </h2>
                    {alertItems.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('dashboard.alerts.none')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                            {alertItems.map((item) => (
                                <li
                                    key={item.key}
                                    className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                                >
                                    <span>{item.text}</span>
                                    <Link
                                        href={item.href}
                                        className="underline underline-offset-4"
                                    >
                                        {item.linkLabel}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-3" data-tour="indicators">
                    <h2 className="text-base font-semibold">
                        {t('dashboard.indicators.title')}
                    </h2>
                    <div className="cascade flex flex-wrap gap-3">
                        {figure(
                            t('dashboard.indicators.active_clients'),
                            indicators.active_clients,
                            t('dashboard.indicators.active_clients_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.orders'),
                            indicators.orders,
                            t('dashboard.indicators.orders_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.kits_ordered'),
                            indicators.kits_ordered,
                            t('dashboard.indicators.kits_ordered_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.kits_delivered'),
                            indicators.kits_delivered,
                            t('dashboard.indicators.kits_delivered_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.kits_remaining'),
                            indicators.kits_remaining,
                            t('dashboard.indicators.kits_remaining_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.sale_total'),
                            money(indicators.sale_total),
                            t('dashboard.indicators.sale_total_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.advances'),
                            money(indicators.advances),
                            t('dashboard.indicators.advances_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.balances'),
                            money(indicators.balances),
                            t('dashboard.indicators.balances_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.refunds'),
                            money(indicators.refunds),
                            t('dashboard.indicators.refunds_help'),
                        )}
                        {figure(
                            t('dashboard.indicators.receivables'),
                            money(indicators.receivables),
                            t('dashboard.indicators.receivables_help'),
                        )}
                        {shops &&
                            figure(
                                t('dashboard.indicators.debts'),
                                money(shops.debts),
                                t('dashboard.indicators.debts_help'),
                            )}
                    </div>
                </section>

                <div data-tour="charts">
                    <DashboardCharts
                        weekly={report.weekly}
                        statuses={indicators.orders_by_status}
                        shops={shops?.summary ?? null}
                        showProfit={profit !== null}
                    />
                </div>

                {profit && (
                    <section className="space-y-3">
                        <h2 className="text-base font-semibold">
                            {t('dashboard.profit.title')}
                        </h2>
                        <div className="cascade grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {(
                                [
                                    'theoretical_margin',
                                    'real_margin',
                                    'projected_margin',
                                    'gap',
                                    'actual_purchase',
                                    'logistics',
                                ] as const
                            ).map((key) =>
                                figure(
                                    t(`dashboard.profit.${key}`),
                                    money(profit[key]),
                                    t(`dashboard.profit.${key}_help`),
                                ),
                            )}
                        </div>
                        <Link
                            href={profitsIndex()}
                            className="text-sm underline underline-offset-4"
                        >
                            {t('dashboard.profit.details')}
                        </Link>
                    </section>
                )}

                {shops && (
                    <section className="space-y-3">
                        <h2 className="text-base font-semibold">
                            {t('dashboard.shops.title')}
                        </h2>
                        {shops.summary.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('dashboard.shops.empty')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                {t('dashboard.shops.shop')}
                                            </th>
                                            {(
                                                [
                                                    'purchase',
                                                    'sale',
                                                    'advance',
                                                    'percent',
                                                    'balance',
                                                ] as const
                                            ).map((column) => (
                                                <th
                                                    key={column}
                                                    className="px-3 py-2 text-right font-medium"
                                                >
                                                    {t(
                                                        `dashboard.shops.${column}`,
                                                    )}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shops.summary.map((row) => (
                                            <tr
                                                key={row.shop}
                                                className="border-t border-border"
                                            >
                                                <td className="px-3 py-2 font-medium">
                                                    {row.shop}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {money(row.purchase)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {money(row.sale)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {money(row.advance)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {row.percent} %
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {money(row.balance)}
                                                </td>
                                            </tr>
                                        ))}
                                        {shopTotals && (
                                            <tr className="border-t-2 border-border bg-muted/30 font-medium">
                                                <td className="px-3 py-2">
                                                    {t('dashboard.shops.total')}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {money(shopTotals.purchase)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {money(shopTotals.sale)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {money(shopTotals.advance)}
                                                </td>
                                                <td className="px-3 py-2" />
                                                <td className="px-3 py-2 text-right">
                                                    {money(shopTotals.balance)}
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                )}

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">
                        {t('dashboard.exports.title')}
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="infoOutline" size="sm">
                            <a href={pdf.url({ query: query() })}>
                                {t('dashboard.exports.pdf')}
                            </a>
                        </Button>
                        {exports.map((report) => (
                            <Button
                                key={report}
                                asChild
                                variant="outline"
                                size="sm"
                            >
                                <a
                                    href={exportShow.url(
                                        { report },
                                        { query: query() },
                                    )}
                                >
                                    {t(`dashboard.exports.${report}`)} ·{' '}
                                    {t('dashboard.exports.csv')}
                                </a>
                            </Button>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}
