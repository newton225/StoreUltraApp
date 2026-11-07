import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    generate,
    index,
    show,
} from '@/actions/App/Http/Controllers/PurchaseController';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import ConfirmAction from '@/components/confirm-action';
import WeekSelect from '@/components/week-select';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { StatusBadge } from '@/components/status-badge';
import { useCanDo } from '@/hooks/use-can-do';

type Purchase = {
    id: number;
    reference: string | null;
    shop: { id: number; name: string };
    week: string;
    status: string;
    expected_total: number;
    actual_total: number;
};

type Paginated = { data: Purchase[]; total: number };

type Props = {
    purchaseOrders: Paginated;
    week: string;
    weeks: string[];
    filters: { shop: number | null; status: string | null };
    statuses: string[];
    shops: { id: number; name: string }[];
    pending_lines: number;
    unassigned_lines: number;
    summary: {
        advances: number;
        shops: {
            shop_id: number;
            name: string;
            purchase: number;
            allocated_advance: number;
            to_finance: number;
        }[];
    };
    accounts: {
        shop_id: number;
        name: string;
        ordered: number;
        received: number;
        paid: number;
        debt: number;
    }[];
};

export default function PurchasesIndex({
    purchaseOrders,
    week,
    weeks,
    filters,
    statuses,
    shops,
    pending_lines: pendingLines,
    unassigned_lines: unassignedLines,
    summary,
    accounts,
}: Props) {
    const canGenerate = useCanDo('purchases.generate');
    const { t } = useTranslation();
    const { money, date } = useFormat();

    setLayoutProps({
        breadcrumbs: [{ title: t('purchases.title'), href: index() }],
    });

    const apply = (patch: Record<string, string>): void => {
        const next: Record<string, string> = {
            week,
            shop: filters.shop === null ? '' : String(filters.shop),
            status: filters.status ?? '',
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
            <Head title={t('purchases.head')} />

            <div className="space-y-8 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('purchases.title')}
                        description={t('purchases.description')}
                    />
                    <ExportMenu view="purchases" query={{ week, ...filters }} />
                </div>

                <div className="grid w-fit min-w-64 gap-1">
                    <Label htmlFor="purchases-week">
                        {t('purchases.generate.week')}
                    </Label>
                    <WeekSelect
                        id="purchases-week"
                        weeks={weeks}
                        value={week}
                        onChange={(value) => apply({ week: value })}
                    />
                </div>

                <section className="space-y-3 rounded-lg border border-border p-4">
                    <h2 className="text-base font-semibold">
                        {t('purchases.generate.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('purchases.generate.description')}
                    </p>
                    <p className="text-sm">
                        {pendingLines > 0
                            ? t('purchases.generate.pending', {
                                  count: pendingLines,
                              })
                            : t('purchases.generate.nothing_pending')}
                        {unassignedLines > 0 && (
                            <span className="block text-destructive">
                                {t('purchases.generate.unassigned', {
                                    count: unassignedLines,
                                })}
                            </span>
                        )}
                    </p>
                    {canGenerate && (
                        <Form
                            {...generate.form()}
                            className="flex flex-wrap items-end gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="week"
                                        value={week}
                                    />
                                    <InputError message={errors.week} />
                                    <ConfirmAction
                                        variant="default"
                                        label={t('purchases.generate.submit')}
                                        title={t(
                                            'purchases.generate.confirm_title',
                                        )}
                                        description={t(
                                            'purchases.generate.confirm_body',
                                        )}
                                        disabled={processing}
                                    />
                                </>
                            )}
                        </Form>
                    )}
                </section>

                <section className="space-y-4">
                    <h2 className="text-base font-semibold">
                        {t('purchases.orders_title')}
                    </h2>
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="filter-shop">
                                {t('purchases.filters.shop')}
                            </Label>
                            <SelectField
                                id="filter-shop"
                                searchable
                                name="shop"
                                defaultValue={String(filters.shop ?? '')}
                                onChange={(value) => apply({ shop: value })}
                                options={[
                                    {
                                        value: '',
                                        label: t('purchases.filters.all_shops'),
                                    },
                                    ...shops.map((shop) => ({
                                        value: String(shop.id),
                                        label: shop.name,
                                    })),
                                ]}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="filter-status">
                                {t('purchases.filters.status')}
                            </Label>
                            <SelectField
                                id="filter-status"
                                name="status"
                                defaultValue={filters.status ?? ''}
                                onChange={(value) => apply({ status: value })}
                                options={[
                                    {
                                        value: '',
                                        label: t(
                                            'purchases.filters.all_statuses',
                                        ),
                                    },
                                    ...statuses.map((status) => ({
                                        value: status,
                                        label: t(
                                            `purchases.statuses.${status}`,
                                        ),
                                    })),
                                ]}
                            />
                        </div>
                    </div>

                    {purchaseOrders.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('purchases.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            {t('purchases.columns.reference')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('purchases.columns.shop')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('purchases.columns.status')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.columns.expected')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.columns.actual')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('purchases.columns.next')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {purchaseOrders.data.map((purchase) => (
                                        <tr
                                            key={purchase.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2">
                                                <Link
                                                    href={show(purchase.id)}
                                                    className="underline underline-offset-4"
                                                >
                                                    {purchase.reference}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-2 font-medium">
                                                {purchase.shop.name}
                                            </td>
                                            <td className="px-4 py-2">
                                                <StatusBadge
                                                    group="purchases"
                                                    status={purchase.status}
                                                />
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(purchase.expected_total)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(purchase.actual_total)}
                                            </td>
                                            <td className="px-4 py-2">
                                                {t(
                                                    `purchases.next_step.${purchase.status}`,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title={`${t('purchases.summary.title')} — ${date(week)}`}
                        description={t('purchases.summary.description')}
                    />
                    <p className="text-sm">
                        {t('purchases.summary.advances')} :{' '}
                        <strong>{money(summary.advances)}</strong>
                    </p>
                    {summary.shops.length > 0 && (
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            {t('purchases.columns.shop')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.summary.purchase')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.summary.allocated')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.summary.to_finance')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {summary.shops.map((row) => (
                                        <tr
                                            key={row.shop_id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2 font-medium">
                                                {row.name}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(row.purchase)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(row.allocated_advance)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {row.to_finance > 0 ? (
                                                    money(row.to_finance)
                                                ) : (
                                                    <span className="text-success-text">
                                                        {row.to_finance < 0
                                                            ? t(
                                                                  'purchases.summary.surplus',
                                                                  {
                                                                      amount: money(
                                                                          -row.to_finance,
                                                                      ),
                                                                  },
                                                              )
                                                            : t(
                                                                  'purchases.summary.covered',
                                                              )}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <p className="text-xs text-muted-foreground">
                        {t('purchases.summary.to_finance_help')}
                    </p>
                </section>

                {accounts.length > 0 && (
                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title={t('purchases.accounts.title')}
                            description={t('purchases.accounts.description')}
                        />
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            {t('purchases.columns.shop')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.accounts.ordered')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.accounts.received')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.accounts.paid')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('purchases.accounts.debt')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {accounts.map((account) => (
                                        <tr
                                            key={account.shop_id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2 font-medium">
                                                {account.name}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(account.ordered)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(account.received)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(account.paid)}
                                            </td>
                                            <td className="px-4 py-2 text-right font-medium">
                                                {money(account.debt)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
