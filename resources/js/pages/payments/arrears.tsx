import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { show as showOrder } from '@/actions/App/Http/Controllers/OrderController';
import { arrears } from '@/actions/App/Http/Controllers/PaymentController';
import ExportMenu from '@/components/export-menu';
import type { PageLinks } from '@/components/pagination';
import Pagination from '@/components/pagination';
import Heading from '@/components/heading';
import SelectField from '@/components/select-field';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type Row = {
    id: number;
    reference: string | null;
    client: string;
    phone: string;
    zone: string;
    delivery_week: string;
    advance_due: number;
    paid_amount: number;
    advance_outstanding: number;
};

type Props = {
    orders: PageLinks & { data: Row[] };
    total_outstanding: number;
    zones: { id: number; name: string }[];
    filters: { zone: number | null };
};

export default function Arrears({
    orders,
    total_outstanding: totalOutstanding,
    zones,
    filters,
}: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();

    setLayoutProps({
        breadcrumbs: [{ title: t('payments.arrears.title'), href: arrears() }],
    });

    return (
        <>
            <Head title={t('payments.arrears.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('payments.arrears.title')}
                        description={t('payments.arrears.description')}
                    />
                    <ExportMenu view="arrears" query={filters} />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="max-w-xs flex-1">
                        <SelectField
                            id="filter-zone"
                            searchable
                            name="zone"
                            defaultValue={String(filters.zone ?? '')}
                            onChange={(value) =>
                                router.get(
                                    arrears.url(),
                                    value ? { zone: value } : {},
                                    { preserveState: true, replace: true },
                                )
                            }
                            options={[
                                {
                                    value: '',
                                    label: t('payments.arrears.all_zones'),
                                },
                                ...zones.map((zone) => ({
                                    value: String(zone.id),
                                    label: zone.name,
                                })),
                            ]}
                        />
                    </div>
                    <p className="text-sm">
                        {t('payments.arrears.total')} :{' '}
                        <strong>{money(totalOutstanding)}</strong>
                    </p>
                </div>

                {orders.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('payments.arrears.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('payments.arrears.columns.client')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('payments.arrears.columns.phone')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('payments.arrears.columns.zone')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('payments.arrears.columns.week')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('payments.arrears.columns.due')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('payments.arrears.columns.paid')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t(
                                            'payments.arrears.columns.outstanding',
                                        )}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2 font-medium">
                                            <Link
                                                href={showOrder(row.id)}
                                                className="underline underline-offset-4"
                                            >
                                                {row.client}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2">
                                            <a
                                                href={`tel:${row.phone}`}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {row.phone}
                                            </a>
                                        </td>
                                        <td className="px-4 py-2">
                                            {row.zone}
                                        </td>
                                        <td className="px-4 py-2">
                                            {date(row.delivery_week)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {money(row.advance_due)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {money(row.paid_amount)}
                                        </td>
                                        <td className="px-4 py-2 text-right font-medium">
                                            {money(row.advance_outstanding)}
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
