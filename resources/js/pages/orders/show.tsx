import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { show as showClient } from '@/actions/App/Http/Controllers/ClientController';
import { index, show } from '@/actions/App/Http/Controllers/OrderController';
import { store as transition } from '@/actions/App/Http/Controllers/OrderTransitionController';
import GuidedTour, { type TourStep } from '@/components/guided-tour';
import Heading from '@/components/heading';
import type { OrderDeliveriesData } from '@/components/order-deliveries';
import type { OrderDocumentsData } from '@/components/order-documents';
import type { OrderProfitData } from '@/components/order-profit';
import OrderProfit from '@/components/order-profit';
import OrderDocuments from '@/components/order-documents';
import OrderDeliveries from '@/components/order-deliveries';
import type { OrderPaymentsData } from '@/components/order-payments';
import OrderPayments from '@/components/order-payments';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { StatusBadge } from '@/components/status-badge';
import TabLinks from '@/components/tab-links';

/** The life of an order, in the order it goes through; a cancelled order leaves it. */
const FLOW = [
    'draft',
    'proforma_issued',
    'advance_received',
    'ordered',
    'received',
    'delivered',
    'settled',
    'closed',
];

type Order = {
    id: number;
    reference: string | null;
    client: { id: number; name: string } | null;
    delivery_week: string;
    status: string;
    kits_count: number;
    total_sale: number;
    advance_due: number;
    total_purchase_expected?: number;
    margin_expected?: number;
    notes: string | null;
    kit: { name: string; version: number };
    allowed_next: string[];
    items: {
        id: number;
        product_name: string;
        unit: string;
        quantity: number;
        unit_purchase_price?: number;
        line_sale_total: number | null;
    }[];
    history: {
        id: number;
        from: string;
        to: string;
        reason: string | null;
        user: string | null;
        at: string | null;
    }[];
};

const ORDER_SHOW_TOUR: TourStep[] = [
    {
        target: 'progress',
        title: 'tours.order_show.progress.title',
        body: 'tours.order_show.progress.body',
    },
    {
        target: 'next_step',
        title: 'tours.order_show.next_step.title',
        body: 'tours.order_show.next_step.body',
    },
    {
        target: 'tabs',
        title: 'tours.order_show.tabs.title',
        body: 'tours.order_show.tabs.body',
    },
];

export default function ShowOrder({
    order,
    payments,
    delivery,
    documents,
    profit,
    canTransition,
    today,
}: {
    order: Order;
    payments: OrderPaymentsData;
    delivery: OrderDeliveriesData;
    documents: OrderDocumentsData;
    profit: OrderProfitData | null;
    canTransition: boolean;
    today: string;
}) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [cancelling, setCancelling] = useState(false);
    const [tab, setTab] = useState('lines');
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    setLayoutProps({
        breadcrumbs: [
            { title: t('orders.title'), href: index() },
            { title: order.reference ?? '', href: show(order.id) },
        ],
    });

    const move = (to: string, extra: Record<string, string> = {}): void => {
        router.post(
            transition.url(order.id),
            { to, ...extra },
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => {
                    setSending(false);
                    setConfirming(null);
                },
                onError: (received) => setErrors(received),
                onSuccess: () => {
                    setErrors({});
                    setCancelling(false);
                    setReason('');
                },
            },
        );
    };

    const nextStep = order.allowed_next.find(
        (status) => status !== 'cancelled',
    );
    const canCancel = order.allowed_next.includes('cancelled');

    return (
        <>
            <Head title={order.reference ?? t('orders.head')} />
            <GuidedTour tour="order-show" steps={ORDER_SHOW_TOUR} />

            <div className="max-w-4xl space-y-8 p-4">
                <div className="space-y-2">
                    <Heading
                        title={order.reference ?? ''}
                        description={t('orders.show.week_of', {
                            date: date(order.delivery_week),
                        })}
                    />
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        {order.client && (
                            <Link
                                href={showClient(order.client.id)}
                                className="font-medium underline underline-offset-4"
                            >
                                {order.client.name}
                            </Link>
                        )}
                        <span className="text-muted-foreground">
                            {t('orders.show.kit', {
                                name: order.kit.name,
                                version: order.kit.version,
                            })}
                        </span>
                    </div>
                </div>

                {order.status === 'cancelled' ? (
                    <p className="rounded-lg border border-border p-4 text-sm">
                        <StatusBadge
                            group="orders"
                            status="cancelled"
                            variant="outline"
                        />{' '}
                        <span className="text-muted-foreground">
                            {t('orders.status_help.cancelled')}
                        </span>
                    </p>
                ) : (
                    <section className="space-y-4">
                        <ol
                            data-tour="progress"
                            aria-label={t('orders.show.progress_label')}
                            className="flex flex-wrap gap-2 text-xs"
                        >
                            {FLOW.map((status, rank) => {
                                const position = FLOW.indexOf(order.status);
                                const done = rank < position;
                                const current = rank === position;

                                return (
                                    <li
                                        key={status}
                                        aria-current={
                                            current ? 'step' : undefined
                                        }
                                        className={cn(
                                            'flex items-center gap-1.5 rounded-full border px-3 py-1 whitespace-nowrap transition-colors duration-300',
                                            current &&
                                                'border-foreground bg-foreground font-semibold text-background',
                                            done &&
                                                'border-border text-foreground',
                                            !done &&
                                                !current &&
                                                'border-dashed border-border text-muted-foreground',
                                        )}
                                    >
                                        {done && (
                                            <Check
                                                className="size-3"
                                                aria-hidden
                                            />
                                        )}
                                        {t(`orders.statuses.${status}`)}
                                    </li>
                                );
                            })}
                        </ol>

                        {canTransition && (nextStep || canCancel) && (
                            <div
                                key={order.status}
                                data-tour="next_step"
                                className="space-y-3 rounded-lg border border-border p-4 fade-in"
                            >
                                {nextStep && (
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="max-w-xl space-y-1">
                                            <div className="text-sm font-semibold">
                                                {t('orders.show.next_step')}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    `orders.status_help.${nextStep}`,
                                                )}
                                            </p>
                                        </div>
                                        <Button
                                            variant="success"
                                            onClick={() =>
                                                setConfirming(nextStep)
                                            }
                                        >
                                            {t(
                                                `orders.show.actions.${nextStep}`,
                                            )}
                                        </Button>
                                    </div>
                                )}
                                <InputError message={errors.to} />

                                {canCancel && !cancelling && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={() => setCancelling(true)}
                                    >
                                        {t('orders.show.cancel')}
                                    </Button>
                                )}

                                {cancelling && (
                                    <div className="grid max-w-md gap-2 border-t border-border pt-3">
                                        <Label htmlFor="reason">
                                            {t('orders.show.cancel_reason')}
                                        </Label>
                                        <Input
                                            id="reason"
                                            value={reason}
                                            onChange={(event) =>
                                                setReason(event.target.value)
                                            }
                                        />
                                        <InputError message={errors.reason} />
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                variant="destructive"
                                                onClick={() =>
                                                    move('cancelled', {
                                                        reason,
                                                    })
                                                }
                                            >
                                                {t(
                                                    'orders.show.cancel_confirm',
                                                )}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    setCancelling(false)
                                                }
                                            >
                                                {t('orders.show.keep')}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </section>
                )}

                <Dialog
                    open={confirming !== null}
                    onOpenChange={(open) => !open && setConfirming(null)}
                >
                    <DialogContent>
                        {confirming && (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {t(`orders.show.actions.${confirming}`)}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t('orders.show.confirm_body', {
                                            reference: order.reference ?? '',
                                            client: order.client?.name ?? '',
                                            status: t(
                                                `orders.statuses.${confirming}`,
                                            ),
                                        })}{' '}
                                        {t(`orders.status_help.${confirming}`)}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        autoFocus
                                        onClick={() => setConfirming(null)}
                                    >
                                        {t('orders.show.confirm_back')}
                                    </Button>
                                    <Button
                                        variant="success"
                                        disabled={sending}
                                        onClick={() => move(confirming)}
                                    >
                                        {t(`orders.show.actions.${confirming}`)}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </DialogContent>
                </Dialog>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {[
                        ['sale', money(order.total_sale)],
                        ['advance_due', money(order.advance_due)],
                        ['paid', money(payments.net_paid)],
                        ['balance', money(payments.balance_due)],
                    ].map(([key, value]) => (
                        <div
                            key={key}
                            className={cn(
                                'rounded-lg border p-3',
                                key === 'balance'
                                    ? 'border-foreground/40'
                                    : 'border-border',
                            )}
                        >
                            <div className="text-xs text-muted-foreground">
                                {t(`orders.show.${key}`)}
                            </div>
                            <div className="mt-1 text-lg font-semibold tabular-nums">
                                {value}
                            </div>
                        </div>
                    ))}
                </div>

                <div data-tour="tabs">
                    <TabLinks
                        label={t('orders.show.tabs_label')}
                        current={tab}
                        onSelect={setTab}
                        tabs={[
                            {
                                key: 'lines',
                                label: t('orders.show.tabs.lines'),
                            },
                            {
                                key: 'payments',
                                label: t('orders.show.tabs.payments'),
                            },
                            {
                                key: 'delivery',
                                label: t('orders.show.tabs.delivery'),
                                count: delivery.can_deliver
                                    ? delivery.remaining_kits
                                    : undefined,
                            },
                            {
                                key: 'documents',
                                label: t('orders.show.tabs.documents'),
                            },
                            ...(profit
                                ? [
                                      {
                                          key: 'profit',
                                          label: t('orders.show.tabs.profit'),
                                      },
                                  ]
                                : []),
                            {
                                key: 'history',
                                label: t('orders.show.tabs.history'),
                            },
                        ]}
                    />
                </div>

                <div key={tab} className="fade-in">
                    {tab === 'lines' && (
                        <section className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                {t('orders.show.lines_help')}
                            </p>
                            <div className="overflow-x-auto rounded-lg border border-border">
                                <table className="w-full min-w-[30rem] text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                {t('orders.show.product')}
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                {t('orders.show.quantity')}
                                            </th>
                                            {profit && (
                                                <th className="px-4 py-2 text-right font-medium">
                                                    {t(
                                                        'orders.show.unit_purchase',
                                                    )}
                                                </th>
                                            )}
                                            <th className="px-4 py-2 text-right font-medium">
                                                {t('orders.show.line_sale')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {order.items.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-t border-border"
                                            >
                                                <td className="px-4 py-2">
                                                    {item.product_name}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    {item.quantity} {item.unit}
                                                </td>
                                                {profit && (
                                                    <td className="px-4 py-2 text-right">
                                                        {money(
                                                            item.unit_purchase_price ??
                                                                0,
                                                        )}
                                                    </td>
                                                )}
                                                <td className="px-4 py-2 text-right">
                                                    {item.line_sale_total !==
                                                    null
                                                        ? money(
                                                              item.line_sale_total,
                                                          )
                                                        : ''}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="border-t border-border bg-muted/50 font-semibold">
                                        <tr>
                                            <td
                                                className="px-4 py-2"
                                                colSpan={profit ? 3 : 2}
                                            >
                                                {t('orders.show.total_sale')}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(order.total_sale)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </section>
                    )}

                    {tab === 'payments' && (
                        <OrderPayments
                            orderId={order.id}
                            payments={payments}
                            today={today}
                        />
                    )}

                    {tab === 'delivery' && (
                        <OrderDeliveries
                            orderId={order.id}
                            delivery={delivery}
                            methods={payments.methods}
                            balanceDue={payments.balance_due}
                            today={today}
                        />
                    )}

                    {tab === 'documents' && (
                        <OrderDocuments documents={documents} />
                    )}

                    {tab === 'profit' && profit && (
                        <OrderProfit profit={profit} />
                    )}

                    {tab === 'history' && (
                        <section className="space-y-3">
                            <h2 className="text-base font-semibold">
                                {t('orders.show.history')}
                            </h2>
                            {order.history.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('orders.show.history_empty')}
                                </p>
                            ) : (
                                <ol className="space-y-2 text-sm">
                                    {order.history.map((change) => (
                                        <li key={change.id}>
                                            {change.at && date(change.at)} :{' '}
                                            {t(
                                                `orders.statuses.${change.from}`,
                                            )}{' '}
                                            →{' '}
                                            {t(`orders.statuses.${change.to}`)}
                                            {change.user &&
                                                ` (${t('orders.show.by', { user: change.user })})`}
                                            {change.reason &&
                                                ` — ${change.reason}`}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}
