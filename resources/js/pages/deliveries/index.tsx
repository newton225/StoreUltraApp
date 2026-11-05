import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { signature as signatureRoute } from '@/actions/App/Http/Controllers/DeliveryController';
import { index } from '@/actions/App/Http/Controllers/DeliveryController';
import { show as showOrder } from '@/actions/App/Http/Controllers/OrderController';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type ToDeliver = {
    id: number;
    reference: string | null;
    client: string;
    phone: string;
    zone: string;
    delivery_week: string;
    kits_count: number;
    remaining_kits: number;
};

type Made = {
    id: number;
    reference: string | null;
    order: { id: number; reference: string | null; client: string | null };
    kits_delivered: number;
    delivered_on: string;
    agent: string | null;
    received_by: string;
    has_signature: boolean;
};

type Props = {
    to_deliver: ToDeliver[];
    deliveries: {
        data: Made[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    zones: { id: number; name: string }[];
    filters: { zone: number | null };
};

export default function DeliveriesIndex({
    to_deliver: toDeliver,
    deliveries,
    zones,
    filters,
}: Props) {
    const { t } = useTranslation();
    const { date } = useFormat();

    setLayoutProps({
        breadcrumbs: [{ title: t('deliveries.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('deliveries.head')} />

            <div className="space-y-8 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('deliveries.title')}
                        description={t('deliveries.description')}
                    />
                    <ExportMenu view="deliveries" query={filters} />
                </div>

                <div className="max-w-xs">
                    <SelectField
                        id="filter-zone"
                        name="zone"
                        defaultValue={String(filters.zone ?? '')}
                        onChange={(value) =>
                            router.get(
                                index.url(),
                                value ? { zone: value } : {},
                                { preserveState: true, replace: true },
                            )
                        }
                        options={[
                            {
                                value: '',
                                label: t('deliveries.filters.all_zones'),
                            },
                            ...zones.map((zone) => ({
                                value: String(zone.id),
                                label: zone.name,
                            })),
                        ]}
                    />
                </div>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">
                        {t('deliveries.to_deliver.title')}
                    </h2>
                    {toDeliver.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('deliveries.to_deliver.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.to_deliver.client')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.to_deliver.phone')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.to_deliver.zone')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.to_deliver.week')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t(
                                                'deliveries.to_deliver.remaining',
                                            )}
                                        </th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {toDeliver.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2 font-medium">
                                                {row.client}
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
                                                {row.remaining_kits} /{' '}
                                                {row.kits_count}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="infoOutline"
                                                >
                                                    <Link
                                                        href={showOrder(row.id)}
                                                    >
                                                        {t(
                                                            'deliveries.to_deliver.open',
                                                        )}
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">
                        {t('deliveries.done.title')}
                    </h2>
                    {deliveries.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('deliveries.done.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.done.reference')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.done.date')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.done.client')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('deliveries.done.kits')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.done.agent')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.done.received_by')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('deliveries.done.signature')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {deliveries.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2">
                                                <Link
                                                    href={showOrder(
                                                        row.order.id,
                                                    )}
                                                    className="underline underline-offset-4"
                                                >
                                                    {row.reference}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-2">
                                                {date(row.delivered_on)}
                                            </td>
                                            <td className="px-4 py-2">
                                                {row.order.client}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {row.kits_delivered}
                                            </td>
                                            <td className="px-4 py-2">
                                                {row.agent ?? '—'}
                                            </td>
                                            <td className="px-4 py-2">
                                                {row.received_by}
                                            </td>
                                            <td className="px-4 py-2">
                                                {row.has_signature && (
                                                    <a
                                                        href={signatureRoute.url(
                                                            row.id,
                                                        )}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="underline underline-offset-4"
                                                    >
                                                        {t(
                                                            'deliveries.done.signed',
                                                        )}
                                                    </a>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination page={deliveries} />
                </section>
            </div>
        </>
    );
}
