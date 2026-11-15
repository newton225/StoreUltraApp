import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { show as showClient } from '@/actions/App/Http/Controllers/ClientController';
import { show as showOrder } from '@/actions/App/Http/Controllers/OrderController';
import { receivables } from '@/actions/App/Http/Controllers/PaymentController';
import Heading from '@/components/heading';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type OrderRow = {
    id: number;
    reference: string | null;
    delivery_week: string;
    status: string;
    total: number;
    advance_due: number;
    paid: number;
    remaining: number;
    advance_outstanding: number;
};

type ClientRow = {
    id: number;
    name: string;
    phone: string;
    zone: string;
    orders_count: number;
    total: number;
    paid: number;
    remaining: number;
    orders: OrderRow[];
};

type Props = {
    clients: ClientRow[];
    totals: { clients: number; orders: number; remaining: number };
    zones: { id: number; name: string }[];
    filters: { zone: number | null };
};

export default function Receivables({
    clients,
    totals,
    zones,
    filters,
}: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();

    setLayoutProps({
        breadcrumbs: [
            { title: t('payments.receivables.title'), href: receivables() },
        ],
    });

    return (
        <>
            <Head title={t('payments.receivables.head')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('payments.receivables.title')}
                    description={t('payments.receivables.description')}
                />

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="max-w-xs flex-1">
                        <SelectField
                            id="filter-zone"
                            searchable
                            name="zone"
                            defaultValue={String(filters.zone ?? '')}
                            onChange={(value) =>
                                router.get(
                                    receivables.url(),
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
                        {t('payments.receivables.summary', {
                            clients: totals.clients,
                            orders: totals.orders,
                        })}{' '}
                        <strong>{money(totals.remaining)}</strong>
                    </p>
                </div>

                {clients.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('payments.receivables.empty')}
                    </p>
                ) : (
                    <div className="space-y-3">
                        {clients.map((client) => (
                            <details
                                key={client.id}
                                className="group rounded-lg border border-border"
                            >
                                <summary className="flex cursor-pointer flex-wrap items-center justify-between gap-x-6 gap-y-1 px-4 py-3 text-sm">
                                    <span className="font-medium">
                                        {client.name}
                                        <span className="ml-2 font-normal text-muted-foreground">
                                            {client.zone} · {client.phone}
                                        </span>
                                    </span>
                                    <span className="text-muted-foreground">
                                        {t('payments.receivables.orders', {
                                            count: client.orders_count,
                                        })}
                                        {' · '}
                                        {t('payments.receivables.paid_of', {
                                            paid: money(client.paid),
                                            total: money(client.total),
                                        })}
                                        {' · '}
                                        <strong className="text-foreground">
                                            {t('payments.receivables.owes', {
                                                amount: money(client.remaining),
                                            })}
                                        </strong>
                                    </span>
                                </summary>

                                <div className="overflow-x-auto border-t border-border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-2 font-medium">
                                                    {t(
                                                        'payments.receivables.columns.order',
                                                    )}
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    {t(
                                                        'payments.receivables.columns.week',
                                                    )}
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    {t(
                                                        'payments.receivables.columns.status',
                                                    )}
                                                </th>
                                                <th className="px-4 py-2 text-right font-medium">
                                                    {t(
                                                        'payments.receivables.columns.total',
                                                    )}
                                                </th>
                                                <th className="px-4 py-2 text-right font-medium">
                                                    {t(
                                                        'payments.receivables.columns.paid',
                                                    )}
                                                </th>
                                                <th className="px-4 py-2 text-right font-medium">
                                                    {t(
                                                        'payments.receivables.columns.advance',
                                                    )}
                                                </th>
                                                <th className="px-4 py-2 text-right font-medium">
                                                    {t(
                                                        'payments.receivables.columns.remaining',
                                                    )}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {client.orders.map((order) => (
                                                <tr
                                                    key={order.id}
                                                    className="border-t border-border"
                                                >
                                                    <td className="px-4 py-2 font-medium">
                                                        <Link
                                                            href={showOrder(
                                                                order.id,
                                                            )}
                                                            className="underline underline-offset-4"
                                                        >
                                                            {order.reference}
                                                        </Link>
                                                    </td>
                                                    <td className="px-4 py-2">
                                                        {date(
                                                            order.delivery_week,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-2">
                                                        <Badge variant="outline">
                                                            {t(
                                                                `orders.statuses.${order.status}`,
                                                            )}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-2 text-right">
                                                        {money(order.total)}
                                                    </td>
                                                    <td className="px-4 py-2 text-right">
                                                        {money(order.paid)}
                                                    </td>
                                                    <td className="px-4 py-2 text-right">
                                                        {order.advance_outstanding >
                                                        0
                                                            ? money(
                                                                  order.advance_outstanding,
                                                              )
                                                            : '—'}
                                                    </td>
                                                    <td className="px-4 py-2 text-right font-medium">
                                                        {money(order.remaining)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    <p className="border-t border-border px-4 py-2 text-sm">
                                        <Link
                                            href={showClient(client.id)}
                                            className="underline underline-offset-4"
                                        >
                                            {t(
                                                'payments.receivables.open_client',
                                            )}
                                        </Link>
                                    </p>
                                </div>
                            </details>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
