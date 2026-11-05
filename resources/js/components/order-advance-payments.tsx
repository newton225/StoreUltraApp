import { router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderAdvancePaymentController';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
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
type Row = { on: boolean; amount: string; reference: string };

const MAX_PAYMENTS = 60;
const today = (): string => new Date().toLocaleDateString('en-CA');

/**
 * Records the advance payment of the small businesses of a zone (sub-prefecture) in one go: every waiting order is
 * offered with what is left to collect (ticked, amount to correct), the method and the date are common, the transaction
 * reference is per payment when the method asks for one (playbook UI-115 for the group action, UI-123 for the
 * modifications detected and the key that stops a replay from taking the money twice).
 */
export default function OrderAdvancePayments({
    zones,
    methods,
}: {
    zones: Zone[];
    methods: Method[];
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const [zone, setZone] = useState('');
    const [method, setMethod] = useState('');
    const [paidOn, setPaidOn] = useState(today);
    const [edits, setEdits] = useState<Record<number, Partial<Row>>>({});
    const [key, setKey] = useState(() => crypto.randomUUID());

    const orders = zones.find((item) => String(item.id) === zone)?.orders ?? [];
    const chosenMethod = methods.find((item) => String(item.id) === method);
    const needsReference = chosenMethod?.requires_reference === true;

    const rowOf = (order: WaitingOrder): Row => ({
        on: true,
        amount: String(order.outstanding),
        reference: '',
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
        method !== '' &&
        picked.length > 0 &&
        !tooMany &&
        picked.every(
            ({ row }) => !needsReference || row.reference.trim() !== '',
        );

    const dirty = method !== '' || Object.keys(edits).length > 0;
    useLeaveGuard(dirty, t('common.form.leave'));

    const reset = (): void => {
        setEdits({});
        setMethod('');
        setKey(crypto.randomUUID());
    };

    return (
        <CollapsiblePanel title={t('orders.collect.open')} defaultOpen={false}>
            <div className="max-w-3xl space-y-4">
                <p className="text-sm text-muted-foreground">
                    {t('orders.collect.description')}
                </p>

                {zones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.collect.none_available')}
                    </p>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
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
                            <div className="grid gap-2">
                                <Label htmlFor="collect-method">
                                    {t('orders.collect.method')}
                                </Label>
                                <SelectField
                                    id="collect-method"
                                    name="payment_method_id"
                                    required
                                    onChange={setMethod}
                                    options={[
                                        {
                                            value: '',
                                            label: t(
                                                'orders.collect.choose_method',
                                            ),
                                        },
                                        ...methods.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                        })),
                                    ]}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="collect-date">
                                    {t('orders.collect.paid_on')}
                                </Label>
                                <Input
                                    id="collect-date"
                                    type="date"
                                    max={today()}
                                    value={paidOn}
                                    onChange={(event) =>
                                        setPaidOn(event.target.value)
                                    }
                                />
                            </div>
                        </div>

                        {orders.length > 0 && (
                            <div className="space-y-2 fade-in">
                                <div className="flex items-center justify-between text-sm">
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
                                    <span className="text-muted-foreground">
                                        {t('orders.collect.selected', {
                                            count: picked.length,
                                            total: money(total),
                                        })}
                                    </span>
                                </div>
                                <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                                    {orders.map((order) => {
                                        const row = rowOf(order);

                                        return (
                                            <li
                                                key={order.id}
                                                className="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2"
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
                                                />
                                                <div className="min-w-40 flex-1">
                                                    <div className="font-medium">
                                                        {order.client}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
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
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    step={1}
                                                    inputMode="numeric"
                                                    className="w-32 text-right tabular-nums"
                                                    value={row.amount}
                                                    disabled={!row.on}
                                                    aria-label={t(
                                                        'orders.collect.amount_for',
                                                        {
                                                            client: order.client,
                                                        },
                                                    )}
                                                    onChange={(event) =>
                                                        patch(order.id, {
                                                            amount: event.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                                {needsReference && (
                                                    <Input
                                                        className="w-44"
                                                        value={row.reference}
                                                        disabled={!row.on}
                                                        placeholder={t(
                                                            'orders.collect.reference',
                                                        )}
                                                        aria-label={t(
                                                            'orders.collect.reference_for',
                                                            {
                                                                client: order.client,
                                                            },
                                                        )}
                                                        onChange={(event) =>
                                                            patch(order.id, {
                                                                reference:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                )}
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
                            disabled={!complete || paidOn === ''}
                            onConfirm={() =>
                                router.post(
                                    store.url(),
                                    {
                                        zone_id: zone,
                                        payment_method_id: method,
                                        paid_on: paidOn,
                                        idempotency_key: key,
                                        payments: picked.map(
                                            ({ order, row }) => ({
                                                order_id: order.id,
                                                amount: Number(row.amount),
                                                reference: needsReference
                                                    ? row.reference.trim()
                                                    : null,
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
