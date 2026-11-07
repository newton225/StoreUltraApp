import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    cancel,
    index,
    store,
} from '@/actions/App/Http/Controllers/CashMovementController';
import type { Closing } from '@/components/cash-closing';
import type { PageLinks } from '@/components/pagination';
import Pagination from '@/components/pagination';
import CashClosing from '@/components/cash-closing';
import type { UnlinkedExpense } from '@/components/cash-link-expenses';
import CashLinkExpenses from '@/components/cash-link-expenses';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
import { FieldLabel } from '@/components/field-label';
import GuidedTour, { type TourStep } from '@/components/guided-tour';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import { HelpHint } from '@/components/hint';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { newIdempotencyKey } from '@/lib/idempotency';
import { useCanDo } from '@/hooks/use-can-do';

type Row = {
    id: number;
    reference: string | null;
    direction: string;
    amount: number;
    occurred_on: string;
    label: string;
    counterparty: string | null;
    document_reference: string | null;
    notes: string | null;
    from_expense: boolean;
    recorder: string | null;
    cancelled: boolean;
    cancel_reason: string | null;
};

type Props = {
    movements: {
        data: Row[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    summary: { opening: number; in: number; out: number; closing: number };
    filters: {
        direction: string | null;
        from: string | null;
        to: string | null;
    };
    today: string;
    current_user_name: string;
    closings: PageLinks & { data: Closing[] };
    last_closed_on: string | null;
    balance_today: number;
    unlinked_expenses: UnlinkedExpense[];
};

/** The day after a closing (YYYY-MM-DD): nothing can be dated before it once the cash box is closed. */
function nextDay(value: string | null): string | undefined {
    if (value === null) {
        return undefined;
    }

    const next = new Date(`${value}T00:00:00Z`);
    next.setUTCDate(next.getUTCDate() + 1);

    return next.toISOString().slice(0, 10);
}

const CASH_TOUR: TourStep[] = [
    {
        target: 'tiles',
        title: 'tours.cash.tiles.title',
        body: 'tours.cash.tiles.body',
    },
    {
        target: 'movement',
        title: 'tours.cash.movement.title',
        body: 'tours.cash.movement.body',
    },
    {
        target: 'closing',
        title: 'tours.cash.closing.title',
        body: 'tours.cash.closing.body',
    },
];

export default function CashIndex({
    movements,
    summary,
    filters,
    today,
    current_user_name: currentUserName,
    closings,
    last_closed_on: lastClosedOn,
    balance_today: balanceToday,
    unlinked_expenses: unlinkedExpenses,
}: Props) {
    const canRecord = useCanDo('cash.record');
    const canCancel = useCanDo('cash.cancel');
    const canClose = useCanDo('cash.close');
    const canLink = useCanDo('cash.link_expenses');
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [idempotencyKey, setIdempotencyKey] = useState(newIdempotencyKey);
    const [cancelling, setCancelling] = useState<number | null>(null);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    setLayoutProps({
        breadcrumbs: [{ title: t('cash.title'), href: index() }],
    });

    const apply = (patch: Partial<Record<string, string>> = {}): void => {
        const next = {
            from,
            to,
            direction: filters.direction ?? '',
            ...patch,
        };

        router.get(
            index.url(),
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, replace: true },
        );
    };

    const tiles: [string, number, string, boolean][] = [
        ['opening', summary.opening, 'opening_help', false],
        ['in', summary.in, 'in_help', false],
        ['out', summary.out, 'out_help', false],
        ['closing', summary.closing, 'closing_help', true],
    ];

    return (
        <>
            <Head title={t('cash.head')} />
            <GuidedTour tour="cash" steps={CASH_TOUR} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('cash.title')}
                        description={t('cash.description')}
                    />
                    <ExportMenu view="cash" query={filters} />
                </div>

                <div
                    className="cascade grid grid-cols-2 gap-3 lg:grid-cols-4"
                    data-tour="tiles"
                >
                    {tiles.map(([key, value, help, strong]) => (
                        <div
                            key={key}
                            className={
                                strong
                                    ? 'rounded-lg border-2 border-primary/40 p-4'
                                    : 'rounded-lg border border-border p-4'
                            }
                        >
                            <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                {t(`cash.summary.${key}`)}
                                <HelpHint label={t(`cash.summary.${help}`)} />
                            </div>
                            <div className="text-xl font-semibold">
                                {money(value)}
                            </div>
                        </div>
                    ))}
                </div>

                {canRecord && (
                    <div data-tour="movement">
                        <CollapsiblePanel title={t('cash.form.open')}>
                            <Form
                                {...store.form()}
                                className="space-y-4"
                                resetOnSuccess
                                onSuccess={() =>
                                    setIdempotencyKey(newIdempotencyKey())
                                }
                            >
                                {({ errors: formErrors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="idempotency_key"
                                            value={idempotencyKey}
                                        />
                                        <div className="grid items-start gap-4 sm:grid-cols-3">
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="cash-direction"
                                                    help={t(
                                                        'cash.form.direction_help',
                                                    )}
                                                >
                                                    {t('cash.form.direction')}
                                                </FieldLabel>
                                                <SelectField
                                                    id="cash-direction"
                                                    name="direction"
                                                    required
                                                    defaultValue="in"
                                                    options={['in', 'out'].map(
                                                        (value) => ({
                                                            value,
                                                            label: t(
                                                                `cash.directions.${value}`,
                                                            ),
                                                        }),
                                                    )}
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.direction
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="cash-amount">
                                                    {t('cash.form.amount')}
                                                </Label>
                                                <Input
                                                    id="cash-amount"
                                                    name="amount"
                                                    type="number"
                                                    min={1}
                                                    step={1}
                                                    inputMode="numeric"
                                                    required
                                                />
                                                <InputError
                                                    message={formErrors.amount}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="cash-date">
                                                    {t('cash.form.date')}
                                                </Label>
                                                <Input
                                                    id="cash-date"
                                                    name="occurred_on"
                                                    type="date"
                                                    max={today}
                                                    min={nextDay(lastClosedOn)}
                                                    defaultValue={today}
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.occurred_on
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="cash-label">
                                                {t('cash.form.label')}
                                            </Label>
                                            <Input
                                                id="cash-label"
                                                name="label"
                                                maxLength={160}
                                                placeholder={t(
                                                    'cash.form.label_placeholder',
                                                )}
                                                required
                                            />
                                            <InputError
                                                message={formErrors.label}
                                            />
                                        </div>

                                        <div className="grid items-start gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="cash-counterparty"
                                                    help={t(
                                                        'cash.form.counterparty_help',
                                                    )}
                                                >
                                                    {t(
                                                        'cash.form.counterparty',
                                                    )}
                                                </FieldLabel>
                                                <Input
                                                    id="cash-counterparty"
                                                    name="counterparty"
                                                    maxLength={120}
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.counterparty
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="cash-document"
                                                    help={t(
                                                        'cash.form.document_reference_help',
                                                    )}
                                                >
                                                    {t(
                                                        'cash.form.document_reference',
                                                    )}
                                                </FieldLabel>
                                                <Input
                                                    id="cash-document"
                                                    name="document_reference"
                                                    maxLength={60}
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.document_reference
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="cash-notes">
                                                {t('cash.form.notes')}
                                            </Label>
                                            <Textarea
                                                rows={3}
                                                id="cash-notes"
                                                name="notes"
                                                maxLength={1000}
                                            />
                                            <InputError
                                                message={formErrors.notes}
                                            />
                                        </div>

                                        <p className="text-sm text-muted-foreground">
                                            {t('cash.form.recorded_note', {
                                                name: currentUserName,
                                            })}
                                        </p>

                                        <ConfirmAction
                                            label={t('cash.form.submit')}
                                            title={t('cash.form.confirm_title')}
                                            description={t(
                                                'cash.form.confirm_body',
                                            )}
                                            disabled={processing}
                                        />
                                    </>
                                )}
                            </Form>
                        </CollapsiblePanel>
                    </div>
                )}

                {canClose && (
                    <CashClosing
                        closings={closings}
                        balanceToday={balanceToday}
                        lastClosedOn={lastClosedOn}
                        today={today}
                        userName={currentUserName}
                    />
                )}

                {canLink && <CashLinkExpenses expenses={unlinkedExpenses} />}

                <form
                    className="grid items-end justify-start gap-3 sm:grid-cols-[10rem_10rem_14rem]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            {t('cash.filters.from')}
                        </span>
                        <Input
                            type="date"
                            value={from}
                            onChange={(event) => {
                                setFrom(event.target.value);
                                apply({ from: event.target.value });
                            }}
                        />
                    </label>
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            {t('cash.filters.to')}
                        </span>
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => {
                                setTo(event.target.value);
                                apply({ to: event.target.value });
                            }}
                        />
                    </label>
                    <SelectField
                        id="cash-filter-direction"
                        name="direction"
                        defaultValue={filters.direction ?? ''}
                        onChange={(value) => apply({ direction: value })}
                        options={[
                            { value: '', label: t('cash.filters.all') },
                            ...['in', 'out'].map((value) => ({
                                value,
                                label: t(`cash.directions.${value}`),
                            })),
                        ]}
                    />
                </form>

                {movements.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('cash.empty')}
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                        {movements.data.map((row) => (
                            <li key={row.id} className="space-y-2 px-4 py-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1 space-y-0.5">
                                        <div className="flex flex-wrap items-center gap-2 font-medium">
                                            <span
                                                className={
                                                    row.cancelled
                                                        ? 'text-muted-foreground line-through'
                                                        : row.direction === 'in'
                                                          ? 'text-green-700 dark:text-green-400'
                                                          : 'text-red-700 dark:text-red-400'
                                                }
                                            >
                                                {row.direction === 'in'
                                                    ? '+'
                                                    : '−'}{' '}
                                                {money(row.amount)}
                                            </span>
                                            <span>{row.label}</span>
                                            {row.from_expense && (
                                                <Badge variant="info">
                                                    {t('cash.linked')}
                                                    <HelpHint
                                                        label={t(
                                                            'cash.linked_help',
                                                        )}
                                                    />
                                                </Badge>
                                            )}
                                            {row.cancelled && (
                                                <Badge variant="danger">
                                                    {t('cash.state_cancelled')}
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {[
                                                row.reference,
                                                date(row.occurred_on),
                                                t(
                                                    `cash.directions.${row.direction}`,
                                                ),
                                                row.counterparty,
                                                row.document_reference,
                                                row.recorder &&
                                                    `${t('cash.columns.recorded_by')} ${row.recorder}`,
                                                row.notes,
                                                row.cancel_reason,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                    </div>
                                    {canCancel &&
                                        !row.cancelled &&
                                        !row.from_expense &&
                                        cancelling !== row.id && (
                                            <Button
                                                variant="ghostDanger"
                                                size="sm"
                                                className="shrink-0"
                                                onClick={() =>
                                                    setCancelling(row.id)
                                                }
                                            >
                                                {t('cash.cancel')}
                                            </Button>
                                        )}
                                </div>
                                {cancelling === row.id && (
                                    <div className="flex max-w-lg flex-wrap items-center gap-2 fade-in">
                                        <Input
                                            value={reason}
                                            onChange={(event) =>
                                                setReason(event.target.value)
                                            }
                                            placeholder={t(
                                                'cash.cancel_reason',
                                            )}
                                            aria-label={t('cash.cancel_reason')}
                                        />
                                        <ConfirmAction
                                            label={t('cash.cancel_confirm')}
                                            title={t('cash.cancel_title')}
                                            description={t('cash.cancel_body')}
                                            destructive
                                            size="sm"
                                            disabled={reason.trim() === ''}
                                            onConfirm={() =>
                                                router.post(
                                                    cancel.url(row.id),
                                                    { reason },
                                                    {
                                                        preserveScroll: true,
                                                        onError: (received) =>
                                                            setErrors(received),
                                                        onSuccess: () => {
                                                            setErrors({});
                                                            setCancelling(null);
                                                            setReason('');
                                                        },
                                                    },
                                                )
                                            }
                                        />
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setCancelling(null);
                                                setReason('');
                                            }}
                                        >
                                            {t('cash.keep')}
                                        </Button>
                                        <InputError message={errors.reason} />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination page={movements} />
            </div>
        </>
    );
}
