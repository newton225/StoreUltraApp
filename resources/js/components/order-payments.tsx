import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/PaymentController';
import CollapsiblePanel from '@/components/collapsible-panel';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import ConfirmAction from '@/components/confirm-action';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { newIdempotencyKey } from '@/lib/idempotency';
import { useTranslation } from '@/hooks/use-translation';
import { FieldLabel } from '@/components/field-label';

export type OrderPaymentsData = {
    items: {
        id: number;
        receipt_number: string | null;
        kind: string;
        amount: number;
        method: string;
        method_id: number;
        reference: string | null;
        paid_on: string;
        payer_name: string | null;
        payer_phone: string | null;
        collector: string | null;
        recorder: string | null;
        reverses_receipt: string | null;
        reversed: boolean;
    }[];
    net_paid: number;
    balance_due: number;
    advance_outstanding: number;
    advance_blocking: boolean;
    methods: { id: number; name: string; requires_reference: boolean }[];
    allowed_kinds: string[];
    client_name: string | null;
    current_user_id: number;
    current_user_name: string;
    /** Empty for someone working in the field: they always collect for themselves. */
    collectors: { id: number; name: string }[];
};

type Props = {
    orderId: number;
    payments: OrderPaymentsData;
    today: string;
};

export default function OrderPayments({ orderId, payments, today }: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [kind, setKind] = useState(payments.allowed_kinds[0] ?? 'advance');
    const [methodId, setMethodId] = useState('');
    const [idempotencyKey, setIdempotencyKey] = useState(newIdempotencyKey);
    const [reverseError, setReverseError] = useState<string | null>(null);

    const needsReference =
        payments.methods.find((method) => String(method.id) === methodId)
            ?.requires_reference ?? false;

    const suggested =
        kind === 'advance'
            ? payments.advance_outstanding || payments.balance_due
            : kind === 'balance'
              ? payments.balance_due
              : 0;

    const canRefund = payments.allowed_kinds.includes('refund');

    const reverse = (payment: OrderPaymentsData['items'][number]): void => {
        router.post(
            store.url(orderId),
            {
                kind: 'refund',
                amount: payment.amount,
                payment_method_id: payment.method_id,
                paid_on: today,
                reverses_payment_id: payment.id,
                // The mode of payment may require a reference: the refund repeats the one of the payment.
                ...(payment.reference ? { reference: payment.reference } : {}),
                idempotency_key: newIdempotencyKey(),
            },
            {
                preserveScroll: true,
                onError: (received) =>
                    setReverseError(Object.values(received)[0] ?? null),
                onSuccess: () => setReverseError(null),
            },
        );
    };

    return (
        <section className="space-y-4">
            <h2 className="text-base font-semibold">
                {t('payments.panel.title')}
            </h2>

            {payments.advance_outstanding > 0 && (
                <p className="rounded-md border border-border p-3 text-sm">
                    {t('payments.panel.advance_outstanding')} :{' '}
                    <strong>{money(payments.advance_outstanding)}</strong>
                </p>
            )}

            {payments.allowed_kinds.length > 0 && (
                <CollapsiblePanel title={t('payments.panel.record')}>
                    <Form
                        {...store.form(orderId)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
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

                                    <div className="grid items-start gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="pay-kind"
                                                help={t(
                                                    'payments.panel.kind_help',
                                                )}
                                            >
                                                {t('payments.panel.kind')}
                                            </FieldLabel>
                                            <SelectField
                                                id="pay-kind"
                                                name="kind"
                                                required
                                                defaultValue={kind}
                                                onChange={setKind}
                                                options={payments.allowed_kinds.map(
                                                    (allowed) => ({
                                                        value: allowed,
                                                        label: t(
                                                            `payments.kinds.${allowed}`,
                                                        ),
                                                    }),
                                                )}
                                            />
                                            <InputError message={errors.kind} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="pay-amount">
                                                {t('payments.panel.amount')}
                                            </Label>
                                            <Input
                                                key={`${kind}-${suggested}`}
                                                id="pay-amount"
                                                name="amount"
                                                type="number"
                                                min={1}
                                                step={1}
                                                defaultValue={
                                                    suggested || undefined
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.amount}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid items-start gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="pay-method">
                                                {t('payments.panel.method')}
                                            </Label>
                                            <SelectField
                                                id="pay-method"
                                                name="payment_method_id"
                                                required
                                                defaultValue=""
                                                onChange={setMethodId}
                                                options={[
                                                    {
                                                        value: '',
                                                        label: t(
                                                            'payments.panel.choose_method',
                                                        ),
                                                    },
                                                    ...payments.methods.map(
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
                                        <div className="grid gap-2">
                                            <Label htmlFor="pay-date">
                                                {t('payments.panel.date')}
                                            </Label>
                                            <Input
                                                id="pay-date"
                                                name="paid_on"
                                                type="date"
                                                max={today}
                                                defaultValue={today}
                                                required
                                            />
                                            <InputError
                                                message={errors.paid_on}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid items-start gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="pay-payer"
                                                help={t(
                                                    'payments.panel.payer_help',
                                                )}
                                            >
                                                {t('payments.panel.payer')}
                                            </FieldLabel>
                                            <Input
                                                id="pay-payer"
                                                name="payer_name"
                                                maxLength={120}
                                                defaultValue={
                                                    payments.client_name ?? ''
                                                }
                                            />
                                            <InputError
                                                message={errors.payer_name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="pay-payer-phone">
                                                {t(
                                                    'payments.panel.payer_phone',
                                                )}
                                            </Label>
                                            <Input
                                                id="pay-payer-phone"
                                                name="payer_phone"
                                                type="tel"
                                                maxLength={30}
                                            />
                                            <InputError
                                                message={errors.payer_phone}
                                            />
                                        </div>
                                    </div>

                                    {payments.collectors.length > 0 ? (
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="pay-collector"
                                                help={t(
                                                    'payments.panel.collector_help',
                                                )}
                                            >
                                                {t('payments.panel.collector')}
                                            </FieldLabel>
                                            <SelectField
                                                id="pay-collector"
                                                searchable
                                                name="collected_by"
                                                defaultValue={String(
                                                    payments.current_user_id,
                                                )}
                                                options={payments.collectors.map(
                                                    (user) => ({
                                                        value: String(user.id),
                                                        label:
                                                            user.id ===
                                                            payments.current_user_id
                                                                ? `${user.name} (${t('payments.panel.you')})`
                                                                : user.name,
                                                    }),
                                                )}
                                            />
                                            <InputError
                                                message={errors.collected_by}
                                            />
                                        </div>
                                    ) : null}

                                    <p className="text-sm text-muted-foreground">
                                        {t('payments.panel.recorded_note', {
                                            name: payments.current_user_name,
                                        })}
                                    </p>

                                    {needsReference && (
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="pay-reference"
                                                help={t(
                                                    'payments.panel.reference_help',
                                                )}
                                            >
                                                {t('payments.panel.reference')}
                                            </FieldLabel>
                                            <Input
                                                id="pay-reference"
                                                name="reference"
                                                required
                                            />
                                            <InputError
                                                message={errors.reference}
                                            />
                                        </div>
                                    )}

                                    <InputError
                                        message={errors.reverses_payment_id}
                                    />

                                    <ConfirmAction
                                        label={t('payments.panel.submit')}
                                        title={t(
                                            'payments.panel.confirm_title',
                                        )}
                                        description={t(
                                            kind === 'refund'
                                                ? 'payments.panel.confirm_refund'
                                                : 'payments.panel.confirm_body',
                                        )}
                                        destructive={kind === 'refund'}
                                        disabled={processing}
                                    />
                                </>
                            );
                        }}
                    </Form>
                </CollapsiblePanel>
            )}

            {reverseError && (
                <p
                    role="alert"
                    className="rounded-md border border-destructive/50 p-3 text-sm text-destructive fade-in"
                >
                    {reverseError}
                </p>
            )}

            {payments.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('payments.panel.empty')}
                </p>
            ) : (
                <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                    {payments.items.map((payment) => (
                        <li
                            key={payment.id}
                            className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                        >
                            <div className="space-y-0.5">
                                <div className="font-medium">
                                    {payment.receipt_number}{' '}
                                    <Badge
                                        variant={
                                            payment.kind === 'refund'
                                                ? 'warning'
                                                : payment.kind === 'balance'
                                                  ? 'success'
                                                  : 'info'
                                        }
                                    >
                                        {t(`payments.kinds.${payment.kind}`)}
                                    </Badge>
                                    {payment.reversed && (
                                        <Badge
                                            variant="danger"
                                            className="ml-1"
                                        >
                                            {t('payments.panel.reversed')}
                                        </Badge>
                                    )}
                                </div>
                                <div className="text-muted-foreground">
                                    {date(payment.paid_on)} · {payment.method}
                                    {payment.reference &&
                                        ` · ${payment.reference}`}
                                </div>
                                <div className="text-muted-foreground">
                                    {[
                                        payment.payer_name
                                            ? `${t('payments.panel.paid_by', { name: payment.payer_name })}${payment.payer_phone ? ` (${payment.payer_phone})` : ''}`
                                            : null,
                                        payment.collector
                                            ? t('payments.panel.collected_by', {
                                                  name: payment.collector,
                                              })
                                            : null,
                                        payment.recorder
                                            ? t('payments.panel.recorded_by', {
                                                  name: payment.recorder,
                                              })
                                            : null,
                                        payment.reverses_receipt
                                            ? t('payments.panel.reversal_of', {
                                                  receipt:
                                                      payment.reverses_receipt,
                                              })
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span
                                    className={
                                        payment.kind === 'refund'
                                            ? 'text-destructive'
                                            : ''
                                    }
                                >
                                    {payment.kind === 'refund' ? '−' : ''}
                                    {money(payment.amount)}
                                </span>
                                {canRefund &&
                                    payment.kind !== 'refund' &&
                                    !payment.reversed && (
                                        <ConfirmAction
                                            label={t('payments.panel.reverse')}
                                            title={t(
                                                'payments.panel.reverse_title',
                                                {
                                                    receipt:
                                                        payment.receipt_number ??
                                                        '',
                                                },
                                            )}
                                            description={t(
                                                'payments.panel.reverse_body',
                                                {
                                                    amount: money(
                                                        payment.amount,
                                                    ),
                                                },
                                            )}
                                            variant="ghost"
                                            size="sm"
                                            destructive
                                            onConfirm={() => reverse(payment)}
                                        />
                                    )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
