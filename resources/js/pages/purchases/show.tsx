import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    cancel,
    index,
    receive,
    send,
    show,
} from '@/actions/App/Http/Controllers/PurchaseController';
import {
    download,
    store as storeDocument,
} from '@/actions/App/Http/Controllers/DocumentController';
import { store as storeShopPayment } from '@/actions/App/Http/Controllers/ShopPaymentController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import ConfirmAction from '@/components/confirm-action';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { StatusBadge } from '@/components/status-badge';

type Item = {
    id: number;
    product_name: string;
    unit: string;
    quantity_ordered: number;
    quantity_received: number;
    shortfall: number;
    unit_price_expected: number;
    unit_price_actual: number | null;
    price_variance: number;
};

type Purchase = {
    id: number;
    reference: string | null;
    shop: { id: number; name: string };
    week: string;
    status: string;
    expected_total: number;
    actual_total: number;
    orders_count: number;
    items: Item[];
    payments: {
        id: number;
        kind: string;
        amount: number;
        method: string;
        reference: string | null;
        paid_on: string;
        payer: string | null;
    }[];
    can_send: boolean;
    can_cancel: boolean;
    can_receive: boolean;
    document: { id: number; number: string } | null;
};

type Props = {
    purchase: Purchase;
    methods: { id: number; name: string; requires_reference: boolean }[];
    today: string;
};

export default function ShowPurchase({ purchase, methods, today }: Props) {
    const { t, locale } = useTranslation();
    const { money, date } = useFormat();
    const [received, setReceived] = useState<
        Record<number, { quantity: string; price: string }>
    >(() =>
        Object.fromEntries(
            purchase.items.map((item) => [
                item.id,
                {
                    quantity: String(item.quantity_received),
                    price:
                        item.unit_price_actual === null
                            ? ''
                            : String(item.unit_price_actual),
                },
            ]),
        ),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [methodId, setMethodId] = useState('');

    setLayoutProps({
        breadcrumbs: [
            { title: t('purchases.title'), href: index() },
            { title: purchase.reference ?? '', href: show(purchase.id) },
        ],
    });

    const needsReference =
        methods.find((method) => String(method.id) === methodId)
            ?.requires_reference ?? false;

    const submitReception = (): void => {
        router.post(
            receive.url(purchase.id),
            {
                items: purchase.items.map((item) => ({
                    id: item.id,
                    quantity_received: received[item.id].quantity,
                    unit_price_actual: received[item.id].price || null,
                })),
            },
            {
                preserveScroll: true,
                onError: (received) => setErrors(received),
                onSuccess: () => setErrors({}),
            },
        );
    };

    return (
        <>
            <Head title={purchase.reference ?? t('purchases.head')} />

            <div className="max-w-3xl space-y-8 p-4">
                <div className="space-y-2">
                    <Heading
                        title={`${purchase.reference ?? ''} — ${purchase.shop.name}`}
                        description={t('purchases.show.week_of', {
                            date: date(purchase.week),
                        })}
                    />
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <StatusBadge
                            group="purchases"
                            status={purchase.status}
                        />
                        <span className="text-muted-foreground">
                            {t('purchases.show.orders_count', {
                                count: purchase.orders_count,
                            })}
                        </span>
                    </div>
                </div>

                {(purchase.can_send || purchase.can_cancel) && (
                    <div className="space-y-2">
                        <div className="flex flex-wrap gap-3">
                            {purchase.can_send && (
                                <ConfirmAction
                                    variant="default"
                                    label={t('purchases.show.send')}
                                    title={t(
                                        'purchases.show.send_confirm_title',
                                        {
                                            shop: purchase.shop.name,
                                        },
                                    )}
                                    description={t(
                                        'purchases.show.send_confirm_body',
                                    )}
                                    onConfirm={() =>
                                        router.post(
                                            send.url(purchase.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                            {purchase.can_cancel && (
                                <ConfirmAction
                                    variant="outline"
                                    destructive
                                    label={t('purchases.show.cancel')}
                                    title={t(
                                        'purchases.show.cancel_confirm_title',
                                    )}
                                    description={t(
                                        'purchases.show.cancel_confirm_body',
                                    )}
                                    onConfirm={() =>
                                        router.post(
                                            cancel.url(purchase.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                        </div>
                        <InputError message={errors.status} />
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    {purchase.document ? (
                        <a
                            href={download.url(purchase.document.id)}
                            target="_blank"
                            rel="noreferrer"
                            className="underline underline-offset-4"
                        >
                            {t('documents.ui.open')} ({purchase.document.number}
                            )
                        </a>
                    ) : (
                        <ConfirmAction
                            variant="outline"
                            size="sm"
                            label={t(
                                'documents.ui.panel.generate.purchase_order',
                            )}
                            title={t('documents.ui.panel.confirm_title', {
                                document: t(
                                    'documents.ui.types.purchase_order',
                                ),
                            })}
                            description={t('documents.ui.panel.confirm_body')}
                            onConfirm={() =>
                                router.post(
                                    storeDocument.url(),
                                    {
                                        type: 'purchase_order',
                                        subject_id: purchase.id,
                                        locale,
                                    },
                                    { preserveScroll: true },
                                )
                            }
                        />
                    )}
                </div>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">
                        {t('purchases.show.lines')}
                    </h2>
                    {purchase.can_receive && (
                        <p className="text-sm text-muted-foreground">
                            {t('purchases.show.receive_help')}
                        </p>
                    )}
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('purchases.show.product')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('purchases.show.ordered')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('purchases.show.received')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('purchases.show.shortfall')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('purchases.show.expected_price')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('purchases.show.actual_price')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('purchases.show.variance')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {purchase.items.map((item, index) => (
                                    <tr
                                        key={item.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2">
                                            {item.product_name}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {item.quantity_ordered} {item.unit}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {purchase.can_receive ? (
                                                <div className="space-y-1">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={
                                                            item.quantity_ordered
                                                        }
                                                        className="ml-auto w-24"
                                                        value={
                                                            received[item.id]
                                                                .quantity
                                                        }
                                                        onChange={(event) =>
                                                            setReceived({
                                                                ...received,
                                                                [item.id]: {
                                                                    ...received[
                                                                        item.id
                                                                    ],
                                                                    quantity:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            })
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `items.${index}.quantity_received`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            ) : (
                                                item.quantity_received
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {item.shortfall}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {money(item.unit_price_expected)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {purchase.can_receive ? (
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    className="ml-auto w-28"
                                                    value={
                                                        received[item.id].price
                                                    }
                                                    placeholder={String(
                                                        item.unit_price_expected,
                                                    )}
                                                    onChange={(event) =>
                                                        setReceived({
                                                            ...received,
                                                            [item.id]: {
                                                                ...received[
                                                                    item.id
                                                                ],
                                                                price: event
                                                                    .target
                                                                    .value,
                                                            },
                                                        })
                                                    }
                                                />
                                            ) : item.unit_price_actual !==
                                              null ? (
                                                money(item.unit_price_actual)
                                            ) : (
                                                ''
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {item.price_variance !== 0
                                                ? money(item.price_variance)
                                                : ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {purchase.can_receive && (
                        <ConfirmAction
                            label={t('purchases.show.receive_submit')}
                            title={t('purchases.show.receive_confirm_title')}
                            description={t(
                                'purchases.show.receive_confirm_body',
                            )}
                            onConfirm={submitReception}
                        />
                    )}
                    <InputError message={errors.status} />
                </section>

                <section className="space-y-2">
                    <h2 className="text-base font-semibold">
                        {t('purchases.show.totals')}
                    </h2>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                {t('purchases.show.expected_total')}
                            </dt>
                            <dd>{money(purchase.expected_total)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                {t('purchases.show.actual_total')}
                            </dt>
                            <dd>{money(purchase.actual_total)}</dd>
                        </div>
                    </dl>
                </section>

                <section className="space-y-4">
                    <h2 className="text-base font-semibold">
                        {t('purchases.show.payments')}
                    </h2>
                    {purchase.payments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('purchases.show.payments_empty')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                            {purchase.payments.map((payment) => (
                                <li
                                    key={payment.id}
                                    className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                                >
                                    <span className="text-muted-foreground">
                                        {date(payment.paid_on)} ·{' '}
                                        {payment.method}
                                        {payment.reference &&
                                            ` · ${payment.reference}`}
                                        {payment.payer && ` · ${payment.payer}`}
                                    </span>
                                    <span>
                                        {payment.kind === 'refund' && '−'}
                                        {money(payment.amount)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}

                    <Form
                        {...storeShopPayment.form(purchase.shop.id)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="space-y-4 rounded-lg border border-border p-4"
                    >
                        {({ processing, errors: formErrors }) => {
                            const formErr: Record<string, string | undefined> =
                                formErrors;

                            return (
                                <>
                                    <h3 className="text-sm font-medium">
                                        {t('purchases.payment.title')}
                                    </h3>
                                    <input
                                        type="hidden"
                                        name="purchase_order_id"
                                        value={purchase.id}
                                    />
                                    <div className="grid items-start gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="sp-kind">
                                                {t('purchases.payment.kind')}
                                            </Label>
                                            <SelectField
                                                id="sp-kind"
                                                name="kind"
                                                required
                                                defaultValue="payment"
                                                options={[
                                                    {
                                                        value: 'payment',
                                                        label: t(
                                                            'purchases.payment.kinds.payment',
                                                        ),
                                                    },
                                                    {
                                                        value: 'refund',
                                                        label: t(
                                                            'purchases.payment.kinds.refund',
                                                        ),
                                                    },
                                                ]}
                                            />
                                            <InputError
                                                message={formErr.kind}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="sp-amount">
                                                {t('purchases.payment.amount')}
                                            </Label>
                                            <Input
                                                id="sp-amount"
                                                name="amount"
                                                type="number"
                                                min={1}
                                                required
                                            />
                                            <InputError
                                                message={formErr.amount}
                                            />
                                        </div>
                                    </div>
                                    <div className="grid items-start gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="sp-method">
                                                {t('purchases.payment.method')}
                                            </Label>
                                            <SelectField
                                                id="sp-method"
                                                name="payment_method_id"
                                                required
                                                defaultValue=""
                                                onChange={setMethodId}
                                                options={[
                                                    {
                                                        value: '',
                                                        label: t(
                                                            'purchases.payment.choose_method',
                                                        ),
                                                    },
                                                    ...methods.map(
                                                        (method) => ({
                                                            value: String(
                                                                method.id,
                                                            ),
                                                            label: method.name,
                                                        }),
                                                    ),
                                                ]}
                                            />
                                            <InputError
                                                message={
                                                    formErr.payment_method_id
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="sp-date">
                                                {t('purchases.payment.date')}
                                            </Label>
                                            <Input
                                                id="sp-date"
                                                name="paid_on"
                                                type="date"
                                                max={today}
                                                defaultValue={today}
                                                required
                                            />
                                            <InputError
                                                message={formErr.paid_on}
                                            />
                                        </div>
                                    </div>
                                    {needsReference && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="sp-reference">
                                                {t(
                                                    'purchases.payment.reference',
                                                )}
                                            </Label>
                                            <Input
                                                id="sp-reference"
                                                name="reference"
                                                required
                                            />
                                            <InputError
                                                message={formErr.reference}
                                            />
                                        </div>
                                    )}
                                    <ConfirmAction
                                        label={t('purchases.payment.submit')}
                                        title={t(
                                            'purchases.payment.confirm_title',
                                        )}
                                        description={t(
                                            'purchases.payment.confirm_body',
                                        )}
                                        disabled={processing}
                                    />
                                </>
                            );
                        }}
                    </Form>
                </section>
            </div>
        </>
    );
}
