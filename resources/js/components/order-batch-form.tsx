import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderBatchController';
import { create } from '@/actions/App/Http/Controllers/OrderController';
import ConfirmAction from '@/components/confirm-action';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid, span } from '@/lib/form-layout';

export type Candidate = {
    id: number;
    name: string;
    reference: string | null;
    already: boolean;
};

type Props = {
    localities: { name: string; count: number }[];
    locality: string;
    batchWeek: string | null;
    candidates: Candidate[];
    kits: { id: number; name: string }[];
    defaults: {
        kits_count: number;
        advance_due: number;
        delivery_week: string;
    };
};

/**
 * Creates the order of a week for all the active TPE of a locality in one go (playbook UI-115): the scope is
 * chosen in a list that shows how many TPE it holds, the TPE are ticked by default, the ones that already have
 * an order that week are shown but cannot be ticked, a sentence says what will happen, and the confirmation
 * names the number. The proformas can be issued at the same time.
 */
export default function OrderBatchForm({
    localities,
    locality,
    batchWeek,
    candidates,
    kits,
    defaults,
}: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [kitId, setKitId] = useState('');
    const [kitsCount, setKitsCount] = useState(String(defaults.kits_count));
    const [advance, setAdvance] = useState(String(defaults.advance_due));
    const [week, setWeek] = useState(batchWeek ?? defaults.delivery_week);
    const [issueProformas, setIssueProformas] = useState(true);
    const [unticked, setUnticked] = useState<number[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // A new locality or a new week gives a new list: everything that can be ticked is ticked again.
    useEffect(() => {
        setUnticked([]);
    }, [locality, batchWeek]);

    const load = (nextLocality: string, nextWeek: string): void => {
        router.get(
            create.url(),
            {
                mode: 'locality',
                ...(nextLocality !== '' ? { locality: nextLocality } : {}),
                ...(nextWeek !== '' ? { batch_week: nextWeek } : {}),
            },
            {
                only: ['candidates', 'locality', 'batchWeek'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const free = candidates.filter((candidate) => !candidate.already);
    const picked = free.filter((candidate) => !unticked.includes(candidate.id));
    const kit = kits.find((item) => String(item.id) === kitId);

    const toggle = (id: number, checked: boolean): void =>
        setUnticked((current) =>
            checked
                ? current.filter((value) => value !== id)
                : [...current, id],
        );

    const submit = (): void => {
        router.post(
            store.url(),
            {
                locality,
                kit_id: kitId,
                kits_count: kitsCount,
                delivery_week: week,
                advance_due: advance,
                client_ids: picked.map((candidate) => candidate.id),
                issue_proformas: issueProformas ? 1 : 0,
            },
            {
                preserveScroll: true,
                onError: (found) => setErrors(found),
                onSuccess: () => setErrors({}),
            },
        );
    };

    const ready =
        locality !== '' &&
        kitId !== '' &&
        picked.length > 0 &&
        Number(kitsCount) >= 1 &&
        week !== '';

    const plural = (key: string, count: number): string =>
        t(`orders.batch.${key}_${count === 1 ? 'one' : 'other'}`, { count });
    const ordersText = plural('orders', picked.length);

    const preview = t('orders.batch.preview', {
        orders: ordersText,
        kits: kitsCount ? plural('kits', Number(kitsCount)) : '…',
        kit: kit ? t('orders.batch.kit_named', { name: kit.name }) : '',
        week: week ? date(week) : '…',
        advance: money(Number(advance) || 0),
        proformas: t(
            issueProformas
                ? 'orders.batch.preview_proformas'
                : 'orders.batch.preview_no_proformas',
        ),
    });

    if (localities.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('orders.batch.no_localities')}
            </p>
        );
    }

    return (
        <div className="space-y-6" data-tour="form_batch">
            <p className="text-sm text-muted-foreground">
                {t('orders.batch.description')}
            </p>

            <div className={formGrid}>
                <div className={`grid gap-2 ${span.half}`}>
                    <FieldLabel
                        htmlFor="batch-locality"
                        help={t('orders.batch.locality_help')}
                    >
                        {t('orders.batch.locality')}
                    </FieldLabel>
                    <SelectField
                        id="batch-locality"
                        name="locality"
                        required
                        defaultValue={locality}
                        onChange={(value) => load(value, week)}
                        options={[
                            {
                                value: '',
                                label: t('orders.batch.choose_locality'),
                            },
                            ...localities.map((item) => ({
                                value: item.name,
                                label: `${item.name} (${item.count})`,
                            })),
                        ]}
                    />
                    <InputError message={errors.locality} />
                </div>

                <div className={`grid gap-2 ${span.half}`}>
                    <FieldLabel
                        htmlFor="batch-kit"
                        help={t('orders.create.kit_help')}
                    >
                        {t('orders.batch.kit')}
                    </FieldLabel>
                    <SelectField
                        id="batch-kit"
                        name="kit_id"
                        required
                        onChange={setKitId}
                        options={[
                            { value: '', label: t('orders.create.choose_kit') },
                            ...kits.map((item) => ({
                                value: String(item.id),
                                label: item.name,
                            })),
                        ]}
                    />
                    <InputError message={errors.kit_id} />
                </div>

                <div className={`grid gap-2 ${span.third}`}>
                    <FieldLabel
                        htmlFor="batch-kits"
                        help={t('orders.create.kits_count_help')}
                    >
                        {t('orders.batch.kits_count')}
                    </FieldLabel>
                    <Input
                        id="batch-kits"
                        type="number"
                        min={1}
                        value={kitsCount}
                        onChange={(event) => setKitsCount(event.target.value)}
                        required
                    />
                    <InputError message={errors.kits_count} />
                </div>

                <div className={`grid gap-2 ${span.third}`}>
                    <FieldLabel
                        htmlFor="batch-week"
                        help={t('orders.create.delivery_week_help')}
                    >
                        {t('orders.batch.week')}
                    </FieldLabel>
                    <Input
                        id="batch-week"
                        type="date"
                        value={week}
                        onChange={(event) => {
                            setWeek(event.target.value);
                            load(locality, event.target.value);
                        }}
                        required
                    />
                    <InputError message={errors.delivery_week} />
                </div>

                <div className={`grid gap-2 ${span.third}`}>
                    <FieldLabel
                        htmlFor="batch-advance"
                        help={t('orders.create.advance_due_help')}
                    >
                        {t('orders.batch.advance')}
                    </FieldLabel>
                    <Input
                        id="batch-advance"
                        type="number"
                        min={0}
                        value={advance}
                        onChange={(event) => setAdvance(event.target.value)}
                        required
                    />
                    <InputError message={errors.advance_due} />
                </div>
            </div>

            {locality !== '' && (
                <section className="space-y-2 fade-in">
                    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <h2 className="text-base font-semibold">
                            {t('orders.batch.clients_title')}
                        </h2>
                        {free.length > 0 && (
                            <span className="flex items-center gap-4">
                                <button
                                    type="button"
                                    className="text-primary underline-offset-4 hover:underline"
                                    onClick={() =>
                                        setUnticked(
                                            picked.length === free.length
                                                ? free.map(
                                                      (candidate) =>
                                                          candidate.id,
                                                  )
                                                : [],
                                        )
                                    }
                                >
                                    {picked.length === free.length
                                        ? t('orders.batch.select_none')
                                        : t('orders.batch.select_all')}
                                </button>
                                <span className="text-muted-foreground">
                                    {t('orders.batch.selected', {
                                        count: picked.length,
                                    })}
                                </span>
                            </span>
                        )}
                    </div>

                    {candidates.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('orders.batch.none_in_locality')}
                        </p>
                    ) : (
                        <ul className="max-h-96 divide-y divide-border overflow-y-auto rounded-lg border border-border text-sm">
                            {candidates.map((candidate) => (
                                <li key={candidate.id}>
                                    <label
                                        className={`flex items-center gap-3 px-4 py-2 ${candidate.already ? 'text-muted-foreground' : 'cursor-pointer'}`}
                                    >
                                        <Checkbox
                                            checked={
                                                !candidate.already &&
                                                !unticked.includes(candidate.id)
                                            }
                                            disabled={candidate.already}
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    candidate.id,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span className="flex-1">
                                            <span className="font-medium">
                                                {candidate.name}
                                            </span>
                                            {candidate.reference && (
                                                <span className="text-muted-foreground">
                                                    {' · '}
                                                    {candidate.reference}
                                                </span>
                                            )}
                                        </span>
                                        {candidate.already && (
                                            <span className="text-xs">
                                                {t('orders.batch.already')}
                                            </span>
                                        )}
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                    <InputError message={errors.client_ids} />
                </section>
            )}

            <label className="flex items-start gap-3 text-sm">
                <Checkbox
                    checked={issueProformas}
                    onCheckedChange={(checked) =>
                        setIssueProformas(checked === true)
                    }
                    className="mt-0.5"
                />
                <span className="space-y-0.5">
                    <span className="font-medium">
                        {t('orders.batch.issue_proformas')}
                    </span>
                    <span className="block text-muted-foreground">
                        {t('orders.batch.issue_proformas_help')}
                    </span>
                </span>
            </label>

            {ready && (
                <p
                    className="rounded-md bg-muted/50 px-3 py-2 text-sm fade-in"
                    aria-live="polite"
                >
                    {preview}
                </p>
            )}

            <ConfirmAction
                label={t('orders.batch.submit')}
                title={t('orders.batch.confirm_title')}
                description={
                    t('orders.batch.confirm_body', {
                        orders: ordersText,
                        week: week ? date(week) : '…',
                    }) +
                    (issueProformas
                        ? t('orders.batch.confirm_body_proformas')
                        : '')
                }
                disabled={!ready}
                onConfirm={submit}
            />
        </div>
    );
}
