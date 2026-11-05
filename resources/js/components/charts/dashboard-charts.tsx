import type { ReactNode } from 'react';
import { BarPanel } from '@/components/charts/bar-panel';
import { ChartCard } from '@/components/charts/chart-card';
import { EmptyChart } from '@/components/charts/empty-chart';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

export type WeeklyRow = {
    week: string;
    sale: number;
    collected: number;
    kits_ordered: number;
    kits_delivered: number;
    theoretical_margin: number | null;
    real_margin: number | null;
};

type ShopFigures = { shop: string; purchase: number; advance: number };

type Props = {
    weekly: WeeklyRow[];
    statuses: Record<string, number>;
    /** The synthesis by shop, or null for someone who does not see the shops. */
    shops: ShopFigures[] | null;
    /** Whether the user may see the profit. */
    showProfit: boolean;
};

/** The two series colours: the first two slots of the validated palette, defined for both themes. */
const FIRST = 'var(--viz-1)';
const SECOND = 'var(--viz-2)';

/**
 * The charts of the dashboard, for the period and the filters chosen: sales and collections by week,
 * kits ordered and delivered by week, orders by state, purchases by shop and, for whoever may see the
 * profit, the margin by week. Every chart can be read as a table too, and when the period has nothing
 * to plot each card still shows where its chart will be, and says why it is empty.
 */
export function DashboardCharts({
    weekly,
    statuses,
    shops,
    showProfit,
}: Props) {
    const { t, locale } = useTranslation();
    const { money } = useFormat();

    const count = new Intl.NumberFormat(locale);
    const dayMonth = new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    });
    const weekLabel = (week: string): string => dayMonth.format(new Date(week));
    const weekOf = (week: string): string =>
        t('dashboard.charts.week_of', { date: weekLabel(week) });

    const weekColumn = t('dashboard.charts.week');
    const nothing = t('dashboard.charts.empty');
    const noWeeks = weekly.length === 0;
    const statusRows = Object.entries(statuses).filter(
        ([, total]) => total > 0,
    );

    const cards: { key: string; node: ReactNode }[] = [
        {
            key: 'kits',
            node: (
                <ChartCard
                    title={t('dashboard.charts.kits_title')}
                    description={t('dashboard.charts.kits_description')}
                    empty={noWeeks}
                    table={{
                        columns: [
                            weekColumn,
                            t('dashboard.charts.kits_ordered'),
                            t('dashboard.charts.kits_delivered'),
                        ],
                        rows: weekly.map((row) => [
                            weekLabel(row.week),
                            count.format(row.kits_ordered),
                            count.format(row.kits_delivered),
                        ]),
                    }}
                >
                    {noWeeks ? (
                        <EmptyChart message={nothing} />
                    ) : (
                        <BarPanel
                            data={weekly}
                            categoryKey="week"
                            series={[
                                {
                                    key: 'kits_ordered',
                                    label: t('dashboard.charts.kits_ordered'),
                                    color: FIRST,
                                },
                                {
                                    key: 'kits_delivered',
                                    label: t('dashboard.charts.kits_delivered'),
                                    color: SECOND,
                                },
                            ]}
                            formatValue={(value) => count.format(value)}
                            formatCategory={weekLabel}
                            formatTooltipLabel={weekOf}
                            ariaLabel={t('dashboard.charts.kits_title')}
                        />
                    )}
                </ChartCard>
            ),
        },
        {
            key: 'statuses',
            node: (
                <ChartCard
                    title={t('dashboard.statuses.title')}
                    description={t('dashboard.charts.statuses_description')}
                    empty={statusRows.length === 0}
                    table={{
                        columns: [
                            t('dashboard.charts.status'),
                            t('dashboard.charts.orders_count'),
                        ],
                        rows: Object.entries(statuses).map(
                            ([status, total]) => [
                                t(`orders.statuses.${status}`),
                                count.format(total),
                            ],
                        ),
                    }}
                >
                    {statusRows.length === 0 ? (
                        <EmptyChart message={nothing} />
                    ) : (
                        <BarPanel
                            data={statusRows.map(([status, total]) => ({
                                status: t(`orders.statuses.${status}`),
                                total,
                            }))}
                            categoryKey="status"
                            series={[
                                {
                                    key: 'total',
                                    label: t('dashboard.charts.orders_count'),
                                    color: FIRST,
                                },
                            ]}
                            horizontal
                            formatValue={(value) => count.format(value)}
                            ariaLabel={t('dashboard.statuses.title')}
                        />
                    )}
                </ChartCard>
            ),
        },
    ];

    if (shops !== null) {
        cards.push({
            key: 'shops',
            node: (
                <ChartCard
                    title={t('dashboard.charts.shops_title')}
                    description={t('dashboard.charts.shops_description')}
                    empty={shops.length === 0}
                    table={{
                        columns: [
                            t('dashboard.shops.shop'),
                            t('dashboard.charts.purchase'),
                            t('dashboard.charts.advance'),
                        ],
                        rows: shops.map((row) => [
                            row.shop,
                            money(row.purchase),
                            money(row.advance),
                        ]),
                    }}
                >
                    {shops.length === 0 ? (
                        <EmptyChart message={nothing} />
                    ) : (
                        <BarPanel
                            data={shops}
                            categoryKey="shop"
                            series={[
                                {
                                    key: 'purchase',
                                    label: t('dashboard.charts.purchase'),
                                    color: FIRST,
                                },
                                {
                                    key: 'advance',
                                    label: t('dashboard.charts.advance'),
                                    color: SECOND,
                                },
                            ]}
                            formatValue={money}
                            ariaLabel={t('dashboard.charts.shops_title')}
                        />
                    )}
                </ChartCard>
            ),
        });
    }

    if (showProfit) {
        cards.push({
            key: 'profit',
            node: (
                <ChartCard
                    title={t('dashboard.charts.profit_title')}
                    description={t('dashboard.charts.profit_description')}
                    empty={noWeeks}
                    table={{
                        columns: [
                            weekColumn,
                            t('dashboard.profit.theoretical_margin'),
                            t('dashboard.profit.real_margin'),
                        ],
                        rows: weekly.map((row) => [
                            weekLabel(row.week),
                            money(row.theoretical_margin ?? 0),
                            money(row.real_margin ?? 0),
                        ]),
                    }}
                >
                    {noWeeks ? (
                        <EmptyChart message={nothing} />
                    ) : (
                        <BarPanel
                            data={weekly}
                            categoryKey="week"
                            series={[
                                {
                                    key: 'theoretical_margin',
                                    label: t(
                                        'dashboard.profit.theoretical_margin',
                                    ),
                                    color: FIRST,
                                },
                                {
                                    key: 'real_margin',
                                    label: t('dashboard.profit.real_margin'),
                                    color: SECOND,
                                },
                            ]}
                            formatValue={money}
                            formatCategory={weekLabel}
                            formatTooltipLabel={weekOf}
                            ariaLabel={t('dashboard.charts.profit_title')}
                        />
                    )}
                </ChartCard>
            ),
        });
    }

    return (
        <section className="space-y-3">
            <h2 className="text-base font-semibold">
                {t('dashboard.charts.title')}
            </h2>
            <div className="cascade grid gap-4 lg:grid-cols-2">
                <div className="lg:col-span-2">
                    <ChartCard
                        title={t('dashboard.charts.sales_title')}
                        description={t('dashboard.charts.sales_description')}
                        empty={noWeeks}
                        table={{
                            columns: [
                                weekColumn,
                                t('dashboard.charts.sale'),
                                t('dashboard.charts.collected'),
                            ],
                            rows: weekly.map((row) => [
                                weekLabel(row.week),
                                money(row.sale),
                                money(row.collected),
                            ]),
                        }}
                    >
                        {noWeeks ? (
                            <EmptyChart message={nothing} />
                        ) : (
                            <BarPanel
                                data={weekly}
                                categoryKey="week"
                                series={[
                                    {
                                        key: 'sale',
                                        label: t('dashboard.charts.sale'),
                                        color: FIRST,
                                    },
                                    {
                                        key: 'collected',
                                        label: t('dashboard.charts.collected'),
                                        color: SECOND,
                                    },
                                ]}
                                formatValue={money}
                                formatCategory={weekLabel}
                                formatTooltipLabel={weekOf}
                                ariaLabel={t('dashboard.charts.sales_title')}
                            />
                        )}
                    </ChartCard>
                </div>
                {cards.map((card, index) => (
                    <div
                        key={card.key}
                        className={
                            cards.length % 2 === 1 && index === cards.length - 1
                                ? 'lg:col-span-2'
                                : undefined
                        }
                    >
                        {card.node}
                    </div>
                ))}
            </div>
        </section>
    );
}
