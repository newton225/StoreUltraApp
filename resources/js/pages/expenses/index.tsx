import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    cancel,
    create,
    index,
    proof,
} from '@/actions/App/Http/Controllers/ExpenseController';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { useVisitBusy } from '@/hooks/use-visit-busy';
import { useCanDo } from '@/hooks/use-can-do';
import { sortQuery } from '@/components/sort-header';
import type { SortState } from '@/components/sort-header';
import SortSelect from '@/components/sort-select';

type Row = {
    id: number;
    type: string;
    amount: number;
    incurred_on: string;
    scope: string;
    week: string | null;
    notes: string | null;
    paid_from_cash: boolean;
    author: string | null;
    orders: number;
    has_proof: boolean;
    cancelled: boolean;
    cancel_reason: string | null;
};

type Props = {
    sort: SortState;
    expenses: {
        data: Row[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    total: number;
    filters: { type: number | null; from: string | null; to: string | null };
    types: { id: number; name: string }[];
};

export default function ExpensesIndex({
    expenses,
    total,
    filters,
    types,
    sort,
}: Props) {
    const canCreate = useCanDo('expenses.create');
    const canCancel = useCanDo('expenses.cancel');
    const { t } = useTranslation();
    const busy = useVisitBusy();
    const { money, date } = useFormat();
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [cancelling, setCancelling] = useState<number | null>(null);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    setLayoutProps({
        breadcrumbs: [{ title: t('expenses.title'), href: index() }],
    });

    const apply = (patch: Record<string, string> = {}): void => {
        const next: Record<string, string> = {
            from,
            to,
            type: filters.type === null ? '' : String(filters.type),
            ...sortQuery(sort),
            ...patch,
        };

        router.get(
            index.url(),
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={t('expenses.head')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={t('expenses.title')}
                        description={t('expenses.description')}
                    />
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <ExportMenu
                            view="expenses"
                            query={{ ...filters, ...sortQuery(sort) }}
                        />
                        {canCreate && (
                            <Button variant="info" asChild>
                                <Link href={create()}>{t('expenses.new')}</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <form
                    className="grid items-end justify-start gap-3 sm:grid-cols-[14rem_10rem_10rem]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <SelectField
                        id="filter-type"
                        name="type"
                        defaultValue={String(filters.type ?? '')}
                        onChange={(value) => apply({ type: value })}
                        options={[
                            {
                                value: '',
                                label: t('expenses.filters.all_types'),
                            },
                            ...types.map((type) => ({
                                value: String(type.id),
                                label: type.name,
                            })),
                        ]}
                    />
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            {t('expenses.filters.from')}
                        </span>
                        <Input
                            type="date"
                            value={from}
                            onChange={(event) => {
                                setFrom(event.target.value);
                                apply({ from: event.target.value });
                            }}
                            aria-label={t('expenses.filters.from')}
                        />
                    </label>
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            {t('expenses.filters.to')}
                        </span>
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => {
                                setTo(event.target.value);
                                apply({ to: event.target.value });
                            }}
                            aria-label={t('expenses.filters.to')}
                        />
                    </label>
                    <SortSelect
                        id="sort-expenses"
                        state={sort}
                        choices={[
                            {
                                sort: '',
                                direction: 'asc',
                                label: t('expenses.sort.newest'),
                            },
                            {
                                sort: 'date',
                                direction: 'asc',
                                label: t('expenses.sort.oldest'),
                            },
                            {
                                sort: 'amount',
                                direction: 'desc',
                                label: t('expenses.sort.amount_high'),
                            },
                            {
                                sort: 'amount',
                                direction: 'asc',
                                label: t('expenses.sort.amount_low'),
                            },
                            {
                                sort: 'type',
                                direction: 'asc',
                                label: t('expenses.sort.type'),
                            },
                            {
                                sort: 'author',
                                direction: 'asc',
                                label: t('expenses.sort.author'),
                            },
                        ]}
                    />
                </form>

                <p className="text-sm">
                    {t('expenses.total')} : <strong>{money(total)}</strong>
                </p>

                {expenses.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('expenses.empty')}
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                        {expenses.data.map((row) => (
                            <li key={row.id} className="space-y-2 px-4 py-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="space-y-0.5">
                                        <div className="font-medium">
                                            {row.type} · {money(row.amount)}{' '}
                                            {row.paid_from_cash && (
                                                <Badge variant="info">
                                                    {t('expenses.from_cash')}
                                                </Badge>
                                            )}{' '}
                                            {row.cancelled && (
                                                <Badge variant="danger">
                                                    {t(
                                                        'expenses.state_cancelled',
                                                    )}
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {date(row.incurred_on)} ·{' '}
                                            {t(`expenses.scopes.${row.scope}`)}
                                            {row.week &&
                                                ` (${date(row.week)})`}{' '}
                                            · {row.orders}{' '}
                                            {t('expenses.columns.orders')}
                                            {row.author && ` · ${row.author}`}
                                            {row.notes && ` · ${row.notes}`}
                                            {row.cancel_reason &&
                                                ` · ${row.cancel_reason}`}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        {row.has_proof && (
                                            <a
                                                href={proof.url(row.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="underline underline-offset-4"
                                            >
                                                {t('expenses.proof')}
                                            </a>
                                        )}
                                        {canCancel &&
                                            !row.cancelled &&
                                            cancelling !== row.id && (
                                                <Button
                                                    variant="ghostDanger"
                                                    size="sm"
                                                    onClick={() =>
                                                        setCancelling(row.id)
                                                    }
                                                >
                                                    {t('expenses.cancel')}
                                                </Button>
                                            )}
                                    </div>
                                </div>
                                {cancelling === row.id && (
                                    <div className="flex max-w-md flex-wrap items-center gap-2">
                                        <Input
                                            value={reason}
                                            onChange={(event) =>
                                                setReason(event.target.value)
                                            }
                                            placeholder={t(
                                                'expenses.cancel_reason',
                                            )}
                                            aria-label={t(
                                                'expenses.cancel_reason',
                                            )}
                                        />
                                        <Button
                                            disabled={busy}
                                            size="sm"
                                            onClick={() =>
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
                                        >
                                            {t('expenses.cancel_confirm')}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setCancelling(null);
                                                setReason('');
                                                setErrors({});
                                            }}
                                        >
                                            {t('expenses.keep')}
                                        </Button>
                                        <InputError message={errors.reason} />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination page={expenses} />
            </div>
        </>
    );
}
