import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { signature as signatureRoute } from '@/actions/App/Http/Controllers/DeliveryController';
import { store } from '@/actions/App/Http/Controllers/DeliveryController';
import CollapsiblePanel from '@/components/collapsible-panel';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import SignatureField from '@/components/signature-field';
import ConfirmAction from '@/components/confirm-action';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { newIdempotencyKey } from '@/lib/idempotency';
import { FieldLabel } from '@/components/field-label';

export type OrderDeliveriesData = {
    items: {
        id: number;
        reference: string | null;
        kits_delivered: number;
        delivered_on: string;
        agent: string | null;
        received_by: string;
        has_signature: boolean;
        notes: string | null;
    }[];
    remaining_kits: number;
    can_deliver: boolean;
    signature_required: boolean;
    is_agent: boolean;
    agents: { id: number; name: string }[];
};

type Props = {
    orderId: number;
    delivery: OrderDeliveriesData;
    methods: { id: number; name: string; requires_reference: boolean }[];
    balanceDue: number;
    today: string;
};

export default function OrderDeliveries({
    orderId,
    delivery,
    methods,
    balanceDue,
    today,
}: Props) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const [methodId, setMethodId] = useState('');
    const [collect, setCollect] = useState('');
    const [idempotencyKey, setIdempotencyKey] = useState(newIdempotencyKey);

    const needsReference =
        collect !== '' &&
        (methods.find((method) => String(method.id) === methodId)
            ?.requires_reference ??
            false);

    return (
        <section className="space-y-4">
            <h2 className="text-base font-semibold">
                {t('deliveries.panel.title')}
            </h2>

            <p className="text-sm text-muted-foreground">
                {delivery.remaining_kits > 0
                    ? t('deliveries.panel.remaining', {
                          count: delivery.remaining_kits,
                      })
                    : t('deliveries.panel.complete')}
            </p>

            {delivery.can_deliver && (
                <CollapsiblePanel title={t('deliveries.panel.record')}>
                    <Form
                        {...store.form(orderId)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setIdempotencyKey(newIdempotencyKey())}
                        className="space-y-4"
                    >
                        {({ processing, errors: formErrors }) => {
                            const errors: Record<string, string | undefined> =
                                formErrors;

                            return (
                                <>
                                    <input
                                        type="hidden"
                                        name="idempotency_key"
                                        value={idempotencyKey}
                                    />
                                    <InputError message={errors.status} />

                                    <div className="grid items-start gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="kits_delivered"
                                                help={t(
                                                    'deliveries.panel.kits_help',
                                                )}
                                            >
                                                {t('deliveries.panel.kits')}
                                            </FieldLabel>
                                            <Input
                                                id="kits_delivered"
                                                name="kits_delivered"
                                                type="number"
                                                min={1}
                                                max={delivery.remaining_kits}
                                                defaultValue={
                                                    delivery.remaining_kits
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.kits_delivered}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="delivered_on">
                                                {t('deliveries.panel.date')}
                                            </Label>
                                            <Input
                                                id="delivered_on"
                                                name="delivered_on"
                                                type="date"
                                                max={today}
                                                defaultValue={today}
                                                required
                                            />
                                            <InputError
                                                message={errors.delivered_on}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid items-start gap-4 sm:grid-cols-2">
                                        <div
                                            className={`grid gap-2 ${delivery.is_agent ? 'sm:col-span-2' : ''}`}
                                        >
                                            <Label htmlFor="received_by">
                                                {t(
                                                    'deliveries.panel.received_by',
                                                )}
                                            </Label>
                                            <Input
                                                id="received_by"
                                                name="received_by"
                                                required
                                            />
                                            <InputError
                                                message={errors.received_by}
                                            />
                                        </div>

                                        {!delivery.is_agent && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="agent_id">
                                                    {t(
                                                        'deliveries.panel.agent',
                                                    )}
                                                </Label>
                                                <SelectField
                                                    id="agent_id"
                                                    searchable
                                                    name="agent_id"
                                                    defaultValue=""
                                                    options={[
                                                        {
                                                            value: '',
                                                            label: t(
                                                                'deliveries.panel.no_agent',
                                                            ),
                                                        },
                                                        ...delivery.agents.map(
                                                            (agent) => ({
                                                                value: String(
                                                                    agent.id,
                                                                ),
                                                                label: agent.name,
                                                            }),
                                                        ),
                                                    ]}
                                                />
                                                <InputError
                                                    message={errors.agent_id}
                                                />
                                            </div>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="delivery-signature">
                                            {t('deliveries.panel.signature')}{' '}
                                            {!delivery.signature_required &&
                                                t(
                                                    'deliveries.panel.signature_optional',
                                                )}
                                        </Label>
                                        <SignatureField
                                            id="delivery-signature"
                                            name="signature"
                                            required={
                                                delivery.signature_required
                                            }
                                        />
                                        <InputError
                                            message={errors.signature}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="delivery-notes">
                                            {t('deliveries.panel.notes')}
                                        </Label>
                                        <Textarea
                                            rows={3}
                                            id="delivery-notes"
                                            name="notes"
                                        />
                                        <InputError message={errors.notes} />
                                    </div>

                                    <fieldset className="space-y-4 rounded-md border border-dashed border-border p-3">
                                        <legend className="px-1 text-sm font-medium">
                                            {t(
                                                'deliveries.panel.collect_title',
                                            )}
                                        </legend>
                                        <div className="grid items-start gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="collect_amount"
                                                    help={t(
                                                        'deliveries.panel.collect_amount_help',
                                                    )}
                                                >
                                                    {t(
                                                        'deliveries.panel.collect_amount',
                                                    )}
                                                </FieldLabel>
                                                <Input
                                                    id="collect_amount"
                                                    name="collect_amount"
                                                    type="number"
                                                    min={1}
                                                    placeholder={String(
                                                        balanceDue,
                                                    )}
                                                    value={collect}
                                                    onChange={(event) =>
                                                        setCollect(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.collect_amount
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="delivery-method">
                                                    {t(
                                                        'deliveries.panel.method',
                                                    )}
                                                </Label>
                                                <SelectField
                                                    id="delivery-method"
                                                    name="payment_method_id"
                                                    prompt
                                                    defaultValue=""
                                                    onChange={(value) => {
                                                        setMethodId(value);

                                                        // Choosing how it is paid means collecting: the balance is offered.
                                                        if (
                                                            value !== '' &&
                                                            collect === ''
                                                        ) {
                                                            setCollect(
                                                                String(
                                                                    balanceDue,
                                                                ),
                                                            );
                                                        }
                                                    }}
                                                    options={[
                                                        {
                                                            value: '',
                                                            label: t(
                                                                'deliveries.panel.choose_method',
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
                                                        errors.payment_method_id
                                                    }
                                                />
                                            </div>
                                        </div>
                                        {needsReference && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="delivery-reference">
                                                    {t(
                                                        'deliveries.panel.reference',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="delivery-reference"
                                                    name="reference"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.reference}
                                                />
                                            </div>
                                        )}
                                    </fieldset>

                                    <ConfirmAction
                                        label={t('deliveries.panel.submit')}
                                        title={t(
                                            'deliveries.panel.confirm_title',
                                        )}
                                        description={t(
                                            'deliveries.panel.confirm_body',
                                        )}
                                        disabled={processing}
                                    />
                                </>
                            );
                        }}
                    </Form>
                </CollapsiblePanel>
            )}

            {delivery.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('deliveries.panel.empty')}
                </p>
            ) : (
                <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                    {delivery.items.map((item) => (
                        <li
                            key={item.id}
                            className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                        >
                            <div className="space-y-0.5">
                                <div className="font-medium">
                                    {item.reference} · {item.kits_delivered}
                                </div>
                                <div className="text-muted-foreground">
                                    {date(item.delivered_on)} ·{' '}
                                    {t('deliveries.panel.received_by_label', {
                                        name: item.received_by,
                                    })}
                                    {item.agent &&
                                        ` · ${t('deliveries.panel.by', { name: item.agent })}`}
                                    {item.notes && ` · ${item.notes}`}
                                </div>
                            </div>
                            {item.has_signature && (
                                <a
                                    href={signatureRoute.url(item.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-sm underline underline-offset-4"
                                >
                                    {t('deliveries.panel.view_signature')}
                                </a>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
