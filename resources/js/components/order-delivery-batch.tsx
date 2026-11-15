import { router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderDeliveryBatchController';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
import { FieldLabel } from '@/components/field-label';
import SelectField from '@/components/select-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLeaveGuard } from '@/hooks/use-leave-guard';
import { useTranslation } from '@/hooks/use-translation';

type ReadyOrder = {
    id: number;
    client: string;
    reference: string | null;
    remaining: number;
    /** How many kits the goods available allow to deliver now. */
    max_kits: number;
    receiver: string | null;
};
type Zone = { id: number; name: string; orders: ReadyOrder[] };
type Agent = { id: number; name: string };
type Row = { on: boolean; kits: string; receiver: string };

const MAX_DELIVERIES = 60;
const today = (): string => new Date().toLocaleDateString('en-CA');

/**
 * Records the delivery of the kits of the small businesses of a zone (sub-prefecture) in one go, as the delivery form
 * of one order does: the zone, the date and the delivery person are chosen once; every line keeps its number of kits
 * (all the kits left by default, lowered for a partial delivery) and the person receiving (the manager of the client
 * by default). The signature and the balance stay on the order page. Playbook UI-115 for the group action, UI-123 for
 * the modifications detected and the key that stops a replay from handing the kits over twice.
 */
export default function OrderDeliveryBatch({
    zones,
    agents,
}: {
    zones: Zone[];
    agents: Agent[];
}) {
    const { t } = useTranslation();
    const [zone, setZone] = useState('');
    const [date, setDate] = useState(today);
    const [agent, setAgent] = useState('');
    const [edits, setEdits] = useState<Record<number, Partial<Row>>>({});
    const [key, setKey] = useState(() => crypto.randomUUID());
    const [processing, setProcessing] = useState(false);

    const orders = zones.find((item) => String(item.id) === zone)?.orders ?? [];

    const rowOf = (order: ReadyOrder): Row => ({
        on: true,
        kits: String(order.remaining),
        receiver: order.receiver ?? '',
        ...edits[order.id],
    });
    const patch = (id: number, change: Partial<Row>): void =>
        setEdits((current) => ({
            ...current,
            [id]: { ...current[id], ...change },
        }));
    const setAll = (on: boolean): void =>
        setEdits((current) => ({
            ...current,
            ...Object.fromEntries(
                orders.map((order) => [order.id, { ...current[order.id], on }]),
            ),
        }));

    const picked = orders
        .map((order) => ({ order, row: rowOf(order) }))
        .filter(({ row }) => row.on);
    const kitsTotal = picked.reduce(
        (sum, { row }) => sum + Number(row.kits),
        0,
    );
    const tooMany = picked.length > MAX_DELIVERIES;
    const complete =
        picked.length > 0 &&
        !tooMany &&
        date !== '' &&
        picked.every(
            ({ order, row }) =>
                Number(row.kits) >= 1 &&
                Number(row.kits) <= order.remaining &&
                Number(row.kits) <= order.max_kits &&
                row.receiver.trim() !== '',
        );

    const dirty =
        agent !== '' || date !== today() || Object.keys(edits).length > 0;
    useLeaveGuard(dirty, t('common.form.leave'));

    const send = (): void => {
        router.post(
            store.url(),
            {
                zone_id: Number(zone),
                idempotency_key: key,
                delivered_on: date,
                agent_id: agent === '' ? null : Number(agent),
                deliveries: picked.map(({ order, row }) => ({
                    order_id: order.id,
                    kits: Number(row.kits),
                    received_by: row.receiver.trim(),
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setEdits({});
                    setAgent('');
                    setDate(today());
                    setKey(crypto.randomUUID());
                },
            },
        );
    };

    return (
        <CollapsiblePanel
            title={t('orders.deliver_batch.open')}
            defaultOpen={false}
        >
            <div className="max-w-5xl space-y-5">
                <p className="max-w-3xl text-sm text-muted-foreground">
                    {t('orders.deliver_batch.description')}
                </p>

                {zones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.deliver_batch.none_available')}
                    </p>
                ) : (
                    <>
                        <div className="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-12">
                            <div className="grid gap-2 lg:col-span-5">
                                <Label htmlFor="deliver-zone">
                                    {t('orders.deliver_batch.zone')}
                                </Label>
                                <SelectField
                                    id="deliver-zone"
                                    searchable
                                    name="zone_id"
                                    required
                                    onChange={(value) => {
                                        setZone(value);
                                        setEdits({});
                                    }}
                                    options={[
                                        {
                                            value: '',
                                            label: t(
                                                'orders.deliver_batch.choose',
                                            ),
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
                                    htmlFor="deliver-date"
                                    help={t(
                                        'orders.deliver_batch.defaults_help',
                                    )}
                                >
                                    {t('orders.deliver_batch.delivered_on')}
                                </FieldLabel>
                                <Input
                                    id="deliver-date"
                                    type="date"
                                    max={today()}
                                    value={date}
                                    onChange={(event) =>
                                        setDate(event.target.value)
                                    }
                                />
                            </div>
                            {agents.length > 0 && (
                                <div className="grid gap-2 sm:col-span-2 lg:col-span-4">
                                    <FieldLabel
                                        htmlFor="deliver-agent"
                                        help={t(
                                            'orders.deliver_batch.defaults_help',
                                        )}
                                    >
                                        {t('orders.deliver_batch.agent')}
                                    </FieldLabel>
                                    <SelectField
                                        id="deliver-agent"
                                        searchable
                                        name="agent_id"
                                        onChange={setAgent}
                                        options={[
                                            {
                                                value: '',
                                                label: t(
                                                    'orders.deliver_batch.agent_none',
                                                ),
                                            },
                                            ...agents.map((item) => ({
                                                value: String(item.id),
                                                label: item.name,
                                            })),
                                        ]}
                                    />
                                </div>
                            )}
                        </div>

                        {orders.length > 0 && (
                            <div className="space-y-2 fade-in">
                                <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span className="flex gap-3">
                                        <button
                                            type="button"
                                            className="text-info-text underline-offset-4 hover:underline"
                                            onClick={() => setAll(true)}
                                        >
                                            {t(
                                                'orders.deliver_batch.select_all',
                                            )}
                                        </button>
                                        <button
                                            type="button"
                                            className="text-info-text underline-offset-4 hover:underline"
                                            onClick={() => setAll(false)}
                                        >
                                            {t(
                                                'orders.deliver_batch.select_none',
                                            )}
                                        </button>
                                    </span>
                                    <span className="text-muted-foreground">
                                        {t('orders.deliver_batch.selected', {
                                            orders: picked.length,
                                            kits: kitsTotal,
                                        })}
                                    </span>
                                </div>

                                <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                                    {orders.map((order, index) => {
                                        const row = rowOf(order);
                                        const noStock =
                                            Number(row.kits) > order.max_kits;
                                        const over =
                                            Number(row.kits) > order.remaining;

                                        return (
                                            <li
                                                key={order.id}
                                                className={`grid items-start gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_7rem_minmax(0,14rem)] ${index % 2 === 1 ? 'bg-muted/30' : ''}`}
                                            >
                                                <label className="flex cursor-pointer items-start gap-3">
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
                                                    />
                                                    <span>
                                                        <span className="font-medium">
                                                            {order.client}
                                                        </span>
                                                        {order.reference && (
                                                            <span className="text-muted-foreground">
                                                                {' · '}
                                                                {
                                                                    order.reference
                                                                }
                                                            </span>
                                                        )}
                                                    </span>
                                                </label>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`deliver-kits-${order.id}`}
                                                        className="text-xs font-normal text-muted-foreground"
                                                    >
                                                        {t(
                                                            'orders.deliver_batch.kits',
                                                        )}{' '}
                                                        <span className="whitespace-nowrap">
                                                            (
                                                            {t(
                                                                'orders.deliver_batch.kits_help',
                                                                {
                                                                    remaining:
                                                                        order.remaining,
                                                                },
                                                            )}
                                                            )
                                                        </span>
                                                    </Label>
                                                    <Input
                                                        id={`deliver-kits-${order.id}`}
                                                        type="number"
                                                        min={1}
                                                        max={order.remaining}
                                                        step={1}
                                                        inputMode="numeric"
                                                        className="tabular-nums"
                                                        disabled={!row.on}
                                                        value={row.kits}
                                                        onChange={(event) =>
                                                            patch(order.id, {
                                                                kits: event
                                                                    .target
                                                                    .value,
                                                            })
                                                        }
                                                    />
                                                    {over && row.on && (
                                                        <p
                                                            role="alert"
                                                            className="text-xs text-destructive"
                                                        >
                                                            {t(
                                                                'orders.deliver_batch.over',
                                                                {
                                                                    max: order.remaining,
                                                                },
                                                            )}
                                                        </p>
                                                    )}
                                                    {!over &&
                                                        noStock &&
                                                        row.on && (
                                                            <p
                                                                role="alert"
                                                                className="text-xs text-destructive"
                                                            >
                                                                {t(
                                                                    'orders.deliver_batch.stock_over',
                                                                    {
                                                                        max: order.max_kits,
                                                                    },
                                                                )}
                                                            </p>
                                                        )}
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`deliver-receiver-${order.id}`}
                                                        className="text-xs font-normal text-muted-foreground"
                                                    >
                                                        {t(
                                                            'orders.deliver_batch.received_by',
                                                        )}
                                                        <span
                                                            className="text-destructive"
                                                            aria-hidden
                                                        >
                                                            {' '}
                                                            *
                                                        </span>
                                                    </Label>
                                                    <Input
                                                        id={`deliver-receiver-${order.id}`}
                                                        maxLength={120}
                                                        disabled={!row.on}
                                                        value={row.receiver}
                                                        onChange={(event) =>
                                                            patch(order.id, {
                                                                receiver:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>

                                {tooMany && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {t('orders.deliver_batch.too_many', {
                                            max: MAX_DELIVERIES,
                                        })}
                                    </p>
                                )}

                                <ConfirmAction
                                    label={t('orders.deliver_batch.submit')}
                                    title={t(
                                        'orders.deliver_batch.confirm_title',
                                    )}
                                    description={t(
                                        'orders.deliver_batch.confirm_body',
                                        {
                                            orders: picked.length,
                                            kits: kitsTotal,
                                        },
                                    )}
                                    disabled={!complete || processing}
                                    onConfirm={send}
                                />
                            </div>
                        )}
                    </>
                )}
            </div>
        </CollapsiblePanel>
    );
}
