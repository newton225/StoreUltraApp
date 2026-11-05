import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/ProfitController';
import GuidedTour, { type TourStep } from '@/components/guided-tour';
import Heading from '@/components/heading';
import { HelpHint } from '@/components/hint';
import SelectField from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type Figures = {
    orders: number;
    sale: number;
    collected: number;
    theoretical_margin: number;
    actual_purchase: number;
    logistics: number;
    real_margin: number;
    gap: number;
};

type Props = {
    totals: Figures;
    rows: (Figures & { label: string })[];
    filters: {
        from: string;
        to: string;
        zone: number | null;
        scope: string;
        group: string;
    };
    groups: string[];
    zones: { id: number; name: string }[];
};

const PROFITS_TOUR: TourStep[] = [
    {
        target: 'filters',
        title: 'tours.profits.filters.title',
        body: 'tours.profits.filters.body',
    },
    {
        target: 'table',
        title: 'tours.profits.table.title',
        body: 'tours.profits.table.body',
    },
];

export default function ProfitsIndex({
    totals,
    rows,
    filters,
    groups,
    zones,
}: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    setLayoutProps({
        breadcrumbs: [{ title: t('expenses.profit.title'), href: index() }],
    });

    const apply = (patch: Record<string, string> = {}): void => {
        const next: Record<string, string> = {
            from,
            to,
            zone: filters.zone === null ? '' : String(filters.zone),
            scope: filters.scope,
            group: filters.group,
            ...patch,
        };

        router.get(
            index.url(),
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, replace: true },
        );
    };

    const label = (value: string): string =>
        filters.group === 'week' ? date(value) : value;

    const cells = (figures: Figures) => (
        <>
            <td className="px-3 py-2 text-right">{figures.orders}</td>
            <td className="px-3 py-2 text-right">{money(figures.sale)}</td>
            <td className="px-3 py-2 text-right">
                {money(figures.theoretical_margin)}
            </td>
            <td className="px-3 py-2 text-right">
                {money(figures.actual_purchase)}
            </td>
            <td className="px-3 py-2 text-right">{money(figures.logistics)}</td>
            <td className="px-3 py-2 text-right">{money(figures.collected)}</td>
            <td className="px-3 py-2 text-right font-medium">
                {money(figures.real_margin)}
            </td>
            <td className="px-3 py-2 text-right">{money(figures.gap)}</td>
        </>
    );

    return (
        <>
            <Head title={t('expenses.profit.head')} />
            <GuidedTour tour="profits" steps={PROFITS_TOUR} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('expenses.profit.title')}
                    description={t('expenses.profit.description')}
                />

                <form
                    data-tour="filters"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="from">
                            {t('expenses.profit.filters.from')}
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
                        <Label htmlFor="to">
                            {t('expenses.profit.filters.to')}
                        </Label>
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
                            {t('expenses.profit.filters.zone')}
                        </Label>
                        <SelectField
                            id="zone"
                            name="zone"
                            defaultValue={String(filters.zone ?? '')}
                            onChange={(value) => apply({ zone: value })}
                            options={[
                                {
                                    value: '',
                                    label: t(
                                        'expenses.profit.filters.all_zones',
                                    ),
                                },
                                ...zones.map((zone) => ({
                                    value: String(zone.id),
                                    label: zone.name,
                                })),
                            ]}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="scope">
                            {t('expenses.profit.filters.scope')}
                        </Label>
                        <SelectField
                            id="scope"
                            name="scope"
                            defaultValue={filters.scope}
                            onChange={(value) => apply({ scope: value })}
                            options={[
                                {
                                    value: 'settled',
                                    label: t(
                                        'expenses.profit.filters.scope_settled',
                                    ),
                                },
                                {
                                    value: 'all',
                                    label: t(
                                        'expenses.profit.filters.scope_all',
                                    ),
                                },
                            ]}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="group">
                            {t('expenses.profit.filters.group')}
                        </Label>
                        <SelectField
                            id="group"
                            name="group"
                            defaultValue={filters.group}
                            onChange={(value) => apply({ group: value })}
                            options={groups.map((group) => ({
                                value: group,
                                label: t(`expenses.profit.groups.${group}`),
                            }))}
                        />
                    </div>
                </form>

                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('expenses.profit.empty')}
                    </p>
                ) : (
                    <div
                        className="overflow-x-auto rounded-lg border border-border"
                        data-tour="table"
                    >
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        {t(
                                            `expenses.profit.groups.${filters.group}`,
                                        )}
                                    </th>
                                    {(
                                        [
                                            'orders',
                                            'sale',
                                            'theoretical_margin',
                                            'actual_purchase',
                                            'logistics',
                                            'collected',
                                            'real_margin',
                                            'gap',
                                        ] as const
                                    ).map((column) => (
                                        <th
                                            key={column}
                                            className="px-3 py-2 text-right font-medium"
                                        >
                                            <span className="inline-flex items-center justify-end gap-1.5">
                                                {t(
                                                    `expenses.profit.columns.${column}`,
                                                )}
                                                <HelpHint
                                                    label={t(
                                                        `expenses.profit.columns_help.${column}`,
                                                    )}
                                                />
                                            </span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr
                                        key={row.label}
                                        className="border-t border-border"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            {label(row.label)}
                                        </td>
                                        {cells(row)}
                                    </tr>
                                ))}
                                <tr className="border-t-2 border-border bg-muted/30 font-medium">
                                    <td className="px-3 py-2">
                                        {t('expenses.profit.total')}
                                    </td>
                                    {cells(totals)}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}

                <p className="text-xs text-muted-foreground">
                    {t('expenses.profit.help')}
                </p>
            </div>
        </>
    );
}
