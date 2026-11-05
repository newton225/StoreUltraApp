import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderAdvancePaymentController';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
import { FieldLabel } from '@/components/field-label';
import SelectField from '@/components/select-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useLeaveGuard } from '@/hooks/use-leave-guard';
import { useTranslation } from '@/hooks/use-translation';

type WaitingOrder = {
    id: number;
    client: string;
    due: number;
    paid: number;
    outstanding: number;
};
type Zone = { id: number; name: string; orders: WaitingOrder[] };
type Method = { id: number; name: string; requires_reference: boolean };
type Collector = { id: number; name: string };
type Row = {
    on: boolean;
    amount: string;
    method: string;
    paidOn: string;
    collector: string;
    reference: string;
    payer: string;
    phone: string;
};

const MAX_PAYMENTS = 60;
const today = (): string => new Date().toLocaleDateString('en-CA');

/** A field with its small visible label above it. */
function Cell({
    label,
    htmlFor,
    required = false,
    children,
}: {
    label: string;
    htmlFor: string;
    required?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-1">
            <Label
                htmlFor={htmlFor}
                className="text-xs font-normal text-muted-foreground"
            >
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden>
                        {' '}
                        *
                    </span>
                )}
            </Label>
            {children}
        </div>
    );
}

/**
 * Records the advance payment of the small businesses of a zone (sub-prefecture) in one go, with the same information
 * as the payment form of one order. Only the zone is chosen once; the amount, the method, the date and who collects
 * are defaults at the top that every line follows until it is changed on that line, and each line also keeps its own
 * payer, phone and, when its method asks for one, transaction reference (playbook UI-115 for the group action, UI-123
 * for the modifications detected and the key that stops a replay from taking the money twice).
 */
export default function OrderAdvancePayments({
    zones,
    methods,
    collectors,
    currentUserId,
}: {
    zones: Zone[];
    methods: Method[];
    collectors: Collector[];
    currentUserId: number;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const me = String(currentUserId);
    const [zone, setZone] = useState('');
    const [defaultMethod, setDefaultMethod] = useState('');
    const [defaultAmount, setDefaultAmount] = useState('');
    const [defaultDate, setDefaultDate] = useState(today);
    const [defaultCollector, setDefaultCollector] = useState(me);
    const [edits, setEdits] = useState<Record<number, Partial<Row>>>({});
    const [key, setKey] = useState(() => crypto.randomUUID());

    const orders = zones.find((item) => String(item.id) === zone)?.orders ?? [];
    const asksReference = (method: string): boolean =>
        methods.find((item) => String(item.id) === method)
            ?.requires_reference === true;

    const rowOf = (order: WaitingOrder): Row => ({
        on: true,
        amount:
            defaultAmount !== '' ? defaultAmount : String(order.outstanding),
        method: defaultMethod,
        paidOn: defaultDate,
        collector: defaultCollector,
        reference: '',
        payer: order.client,
        phone: '',
        ...edits[order.id],
    });
    const patch = (id: number, change: Partial<Row>): void =>
        setEdits((current) => ({
            ...current,
            [id]: { ...current[id], ...change },
        }));

    const picked = orders
        .map((order) => ({ order, row: rowOf(order) }))
        .filter(({ row }) => row.on && Number(row.amount) > 0);
    const total = picked.reduce((sum, { row }) => sum + Number(row.amount), 0);
    const tooMany = picked.length > MAX_PAYMENTS;
    const complete =
        picked.length > 0 &&
        !tooMany &&
        picked.every(
            ({ row }) =>
                row.method !== '' &&
                row.paidOn !== '' &&
                (!asksReference(row.method) || row.reference.trim() !== ''),
        );

    const dirty =
        defaultMethod !== '' ||
        defaultAmount !== '' ||
        defaultCollector !== me ||
        defaultDate !== today() ||
        Object.keys(edits).length > 0;
    useLeaveGuard(dirty, t('common.form.leave'));

    const reset = (): void => {
        setEdits({});
        setDefaultMethod('');
        setDefaultAmount('');
        setDefaultCollector(me);
        setDefaultDate(today());
        setKey(crypto.randomUUID());
    };

    const collectorOptions = collectors.map((user) => ({
        value: String(user.id),
        label:
            user.id === currentUserId
                ? `${user.name} (${t('payments.panel.you')})`
                : user.name,
    }));
    const methodOptions = [
        { value: '', label: t('orders.collect.choose_method') },
        ...methods.map((item) => ({
            value: String(item.id),
            label: item.name,
        })),
    ];

    return (
        <CollapsiblePanel title={t('orders.collect.open')} defaultOpen={false}>
            <div className="max-w-5xl space-y-5">
                <p className="max-w-3xl text-sm text-muted-foreground">
                    {t('orders.collect.description')}
                </p>

                {zones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.collect.none_available')}
                    </p>
                ) : (
                    <>
                        <div className="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-6">
                            <div className="grid gap-2 lg:col-span-3">
                                <Label htmlFor="collect-zone">
                                    {t('orders.collect.zone')}
                                </Label>
                                <SelectField
                                    id="collect-zone"
                                    name="zone_id"
                                    required
                                    onChange={(value) => {
                                        setZone(value);
                                        setEdits({});
                                    }}
                                    options={[
                                        {
                                            value: '',
                                            label: t('orders.collect.choose'),
                                        },
                                        ...zones.map((item) => ({
                                            value: String(item.id),
                                            label: `${item.name} (${item.orders.length})`,
                                        })),
                                    ]}
                                />
                            </div>
                            <div className="grid gap-2 lg:col-span-3">
                                <FieldLabel
                                    htmlFor="collect-method"
                                    help={t('orders.collect.defaults_help')}
                                >
                                    {t('orders.collect.method')}
                                </FieldLabel>
                                <SelectField
                                    id="collect-method"
                                    name="payment_method_id"
                                    required
                                    onChange={setDefaultMethod}
                                    options={methodOptions}
                                />
                            </div>
                            <div className="grid gap-2 lg:col-span-2">
                                <FieldLabel
                                    htmlFor="collect-amount"
                                    help={t('orders.collect.amount_all_help')}
                                >
                                    {t('orders.collect.amount_all')}
                                </FieldLabel>
                                <Input
                                    id="collect-amount"
                                    type="number"
                                    min={1}
                                    step={1}
                                    inputMode="numeric"
                                    className="tabular-nums"
                                    placeholder={t(
                                        'orders.collect.amount_all_placeholder',
                                    )}
                                    value={defaultAmount}
                                    onChange={(event) =>
                                        setDefaultAmount(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2 lg:col-span-2">
                                <FieldLabel
                                    htmlFor="collect-date"
                                    help={t('orders.collect.defaults_help')}
                                >
                                    {t('orders.collect.paid_on')}
                                </FieldLabel>
                                <Input
                                    id="collect-date"
                                    type="date"
                                    max={today()}
                                    value={defaultDate}
                                    onChange={(event) =>
                                        setDefaultDate(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2 sm:col-span-2 lg:col-span-2">
                                <FieldLabel
                                    htmlFor="collect-collector"
                                    help={t('orders.collect.defaults_help')}
                                >
                                    {t('payments.panel.collector')}
                                </FieldLabel>
                                <SelectField
                                    id="collect-collector"
                                    name="collected_by"
                                    defaultValue={me}
                                    onChange={setDefaultCollector}
                                    options={collectorOptions}
                                />
                            </div>
                        </div>

                        {orders.length > 0 && (
                            <div className="space-y-2 fade-in">
                                <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <button
                                        type="button"
                                        className="text-info-text underline-offset-4 hover:underline"
                                        onClick={() =>
                                            setEdits((current) =>
                                                Object.fromEntries(
                                                    Object.entries(current).map(
                                                        ([id, row]) => [
                                                            id,
                                                            {
                                                                ...row,
                                                                on: true,
                                                            },
                                                        ],
                                                    ),
                                                ),
                                            )
                                        }
                                    >
                                        {t('orders.collect.select_all')}
                                    </button>
                                    <span className="text-muted-foreground tabular-nums">
                                        {t('orders.collect.selected', {
                                            count: picked.length,
                                            total: money(total),
                                        })}
                                    </span>
                                </div>

                                <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border text-sm">
                                    {orders.map((order) => {
                                        const row = rowOf(order);
                                        const id = `collect-${order.id}`;
                                        const wantsReference = asksReference(
                                            row.method,
                                        );

                                        const anyReference = orders.some(
                                            (other) =>
                                                asksReference(
                                                    rowOf(other).method,
                                                ),
                                        );
                                        return (
                                            <li
                                                key={order.id}
                                                className={`grid gap-x-4 gap-y-3 px-4 py-4 even:bg-muted/60 md:grid-cols-[1.25rem_minmax(0,1fr)_8rem_10rem_13rem] md:items-end ${row.on ? '' : 'opacity-60'}`}
                                            >
                                                <Checkbox
                                                    checked={row.on}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        patch(order.id, {
                                                            on:
                                                                checked ===
                                                                true,
                                                        })
                                                    }
                                                    aria-label={order.client}
                                                    className="md:mb-2.5"
                                                />
                                                <div className="md:pb-1.5">
                                                    <div className="font-medium">
                                                        {order.client}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground tabular-nums">
                                                        {t(
                                                            'orders.collect.outstanding',
                                                            {
                                                                amount: money(
                                                                    order.outstanding,
                                                                ),
                                                                due: money(
                                                                    order.due,
                                                                ),
                                                            },
                                                        )}
                                                    </div>
                                                </div>
                                                <Cell
                                                    label={t(
                                                        'orders.collect.amount',
                                                    )}
                                                    htmlFor={`${id}-amount`}
                                                >
                                                    <Input
                                                        id={`${id}-amount`}
                                                        type="number"
                                                        min={1}
                                                        step={1}
                                                        inputMode="numeric"
                                                        className="text-right tabular-nums"
                                                        value={row.amount}
                                                        disabled={!row.on}
                                                        onChange={(event) =>
                                                            patch(order.id, {
                                                                amount: event
                                                                    .target
                                                                    .value,
                                                            })
                                                        }
                                                    />
                                                </Cell>
                                                <Cell
                                                    label={t(
                                                        'orders.collect.paid_on',
                                                    )}
                                                    htmlFor={`${id}-date`}
                                                >
                                                    <Input
                                                        id={`${id}-date`}
                                                        type="date"
                                                        max={today()}
                                                        value={row.paidOn}
                                                        disabled={!row.on}
                                                        onChange={(event) =>
                                                            patch(order.id, {
                                                                paidOn: event
                                                                    .target
                                                                    .value,
                                                            })
                                                        }
                                                    />
                                                </Cell>
                                                <Cell
                                                    label={t(
                                                        'payments.panel.collector',
                                                    )}
                                                    htmlFor={`${id}-collector`}
                                                >
                                                    <SelectField
                                                        id={`${id}-collector`}
                                                        name={`collector-${order.id}`}
                                                        key={row.collector}
                                                        defaultValue={
                                                            row.collector
                                                        }
                                                        onChange={(value) =>
                                                            patch(order.id, {
                                                                collector:
                                                                    value,
                                                            })
                                                        }
                                                        options={
                                                            collectorOptions
                                                        }
                                                    />
                                                </Cell>

                                                <div
                                                    className={`grid gap-x-4 gap-y-3 md:col-span-4 md:col-start-2 ${anyReference ? 'md:grid-cols-[minmax(0,1fr)_8.5rem_10rem_11rem]' : 'md:grid-cols-[minmax(0,1fr)_10rem_13rem]'}`}
                                                >
                                                    <Cell
                                                        label={t(
                                                            'payments.panel.payer',
                                                        )}
                                                        htmlFor={`${id}-payer`}
                                                    >
                                                        <Input
                                                            id={`${id}-payer`}
                                                            maxLength={120}
                                                            value={row.payer}
                                                            disabled={!row.on}
                                                            onChange={(event) =>
                                                                patch(
                                                                    order.id,
                                                                    {
                                                                        payer: event
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </Cell>
                                                    <Cell
                                                        label={t(
                                                            'payments.panel.payer_phone',
                                                        )}
                                                        htmlFor={`${id}-phone`}
                                                    >
                                                        <Input
                                                            id={`${id}-phone`}
                                                            type="tel"
                                                            maxLength={30}
                                                            value={row.phone}
                                                            disabled={!row.on}
                                                            onChange={(event) =>
                                                                patch(
                                                                    order.id,
                                                                    {
                                                                        phone: event
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </Cell>
                                                    <Cell
                                                        label={t(
                                                            'orders.collect.method',
                                                        )}
                                                        htmlFor={`${id}-method`}
                                                    >
                                                        <SelectField
                                                            id={`${id}-method`}
                                                            name={`method-${order.id}`}
                                                            key={row.method}
                                                            required
                                                            defaultValue={
                                                                row.method
                                                            }
                                                            onChange={(value) =>
                                                                patch(
                                                                    order.id,
                                                                    {
                                                                        method: value,
                                                                    },
                                                                )
                                                            }
                                                            options={
                                                                methodOptions
                                                            }
                                                        />
                                                    </Cell>
                                                    {wantsReference ? (
                                                        <Cell
                                                            label={t(
                                                                'orders.collect.reference',
                                                            )}
                                                            htmlFor={`${id}-reference`}
                                                            required
                                                        >
                                                            <Input
                                                                id={`${id}-reference`}
                                                                value={
                                                                    row.reference
                                                                }
                                                                disabled={
                                                                    !row.on
                                                                }
                                                                aria-required
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    patch(
                                                                        order.id,
                                                                        {
                                                                            reference:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </Cell>
                                                    ) : anyReference ? (
                                                        <span />
                                                    ) : null}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}

                        {tooMany && (
                            <p
                                role="alert"
                                className="text-sm text-destructive-text"
                            >
                                {t('orders.collect.too_many', {
                                    max: MAX_PAYMENTS,
                                })}
                            </p>
                        )}

                        <ConfirmAction
                            label={t('orders.collect.confirm')}
                            title={t('orders.collect.confirm_title')}
                            description={t('orders.collect.confirm_body', {
                                count: picked.length,
                                total: money(total),
                            })}
                            disabled={!complete}
                            onConfirm={() =>
                                router.post(
                                    store.url(),
                                    {
                                        zone_id: zone,
                                        idempotency_key: key,
                                        payments: picked.map(
                                            ({ order, row }) => ({
                                                order_id: order.id,
                                                amount: Number(row.amount),
                                                payment_method_id: row.method,
                                                paid_on: row.paidOn,
                                                collected_by: row.collector,
                                                reference: asksReference(
                                                    row.method,
                                                )
                                                    ? row.reference.trim()
                                                    : null,
                                                payer_name:
                                                    row.payer.trim() || null,
                                                payer_phone:
                                                    row.phone.trim() || null,
                                            }),
                                        ),
                                    },
                                    {
                                        preserveScroll: true,
                                        onSuccess: reset,
                                    },
                                )
                            }
                        />
                    </>
                )}
            </div>
        </CollapsiblePanel>
    );
}
