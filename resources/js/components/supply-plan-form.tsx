import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { store as storePlan } from '@/actions/App/Http/Controllers/SupplyPlanController';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid, span } from '@/lib/form-layout';

type Defaults = {
    kits_per_cycle: number;
    advance_amount: number;
    frequency: string;
    starts_on: string;
};

type Values = {
    kit_id: string;
    kits_per_cycle: string;
    advance_amount: string;
    frequency: string;
    starts_on: string;
    stop: string;
    ends_on: string;
    cycles_count: string;
};

const FREQUENCIES = ['weekly', 'biweekly', 'monthly'];
const STOPS = ['never', 'date', 'count'];

/**
 * Sets up the automatic delivery of a client: which kit, how many, how often, from when and until when. The form
 * says in one sentence what it will do (it follows what is typed), and the end is one choice (never, a date or a
 * number of deliveries) instead of two fields that could contradict each other. Mistakes are shown under the field
 * by the server's own messages, not by the browser's bubble.
 */
export default function SupplyPlanForm({
    clientId,
    defaults,
    kits,
}: {
    clientId: number;
    defaults: Defaults;
    kits: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [values, setValues] = useState<Values>({
        kit_id: '',
        kits_per_cycle: String(defaults.kits_per_cycle),
        advance_amount: String(defaults.advance_amount),
        frequency: defaults.frequency,
        starts_on: defaults.starts_on,
        stop: 'never',
        ends_on: '',
        cycles_count: '',
    });

    const sync = (form: HTMLFormElement): void => {
        const data = new FormData(form);
        const read = (key: keyof Values): string => {
            const value = data.get(key);

            return typeof value === 'string' ? value : '';
        };

        setValues((current) => ({
            kit_id: read('kit_id'),
            kits_per_cycle: read('kits_per_cycle'),
            advance_amount: read('advance_amount'),
            frequency: read('frequency'),
            starts_on: read('starts_on'),
            stop: read('stop') || current.stop,
            ends_on: read('ends_on'),
            cycles_count: read('cycles_count'),
        }));
    };

    const kit = kits.find((item) => String(item.id) === values.kit_id);
    const hasDate = values.stop === 'date';
    const hasCount = values.stop === 'count';
    const third = hasDate || hasCount;

    const stopText = hasDate
        ? values.ends_on
            ? t('orders.plans.preview.stop_date', {
                  date: date(values.ends_on),
              })
            : t('orders.plans.preview.stop_date_missing')
        : hasCount
          ? values.cycles_count
              ? t(
                    `orders.plans.preview.stop_count_${Number(values.cycles_count) === 1 ? 'one' : 'other'}`,
                    { count: values.cycles_count },
                )
              : t('orders.plans.preview.stop_count_missing')
          : t('orders.plans.preview.stop_never');

    const preview = t('orders.plans.preview.sentence', {
        rhythm: t(`orders.plans.frequencies.${values.frequency}`),
        start: values.starts_on ? date(values.starts_on) : '…',
        kits: values.kits_per_cycle
            ? t(
                  `orders.plans.preview.kits_${Number(values.kits_per_cycle) === 1 ? 'one' : 'other'}`,
                  { count: values.kits_per_cycle },
              )
            : '…',
        kit: kit ? t('orders.plans.preview.kit_named', { name: kit.name }) : '',
        advance: money(Number(values.advance_amount) || 0),
        stop: stopText,
    });

    return (
        <Form
            {...storePlan.form(clientId)}
            options={{ preserveScroll: true }}
            noValidate
            onChange={(event) => sync(event.currentTarget)}
            className="space-y-4 rounded-lg border border-border p-4"
        >
            {({ processing, errors: formErrors }) => {
                const errors: Record<string, string | undefined> = formErrors;

                return (
                    <>
                        <h3 className="text-sm font-medium">
                            {t('orders.plans.new_title')}
                        </h3>

                        <div className={formGrid}>
                            <div className={`grid gap-2 ${span.half}`}>
                                <FieldLabel
                                    htmlFor="plan-kit"
                                    help={t('orders.plans.kit_help')}
                                >
                                    {t('orders.plans.kit')}
                                </FieldLabel>
                                <SelectField
                                    id="plan-kit"
                                    name="kit_id"
                                    required
                                    options={[
                                        {
                                            value: '',
                                            label: t('orders.plans.choose_kit'),
                                        },
                                        ...kits.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                        })),
                                    ]}
                                />
                                <InputError message={errors.kit_id} />
                            </div>

                            <div className={`grid gap-2 ${span.half}`}>
                                <FieldLabel
                                    htmlFor="frequency"
                                    help={t('orders.plans.frequency_help')}
                                >
                                    {t('orders.plans.frequency')}
                                </FieldLabel>
                                <SelectField
                                    id="frequency"
                                    name="frequency"
                                    required
                                    defaultValue={defaults.frequency}
                                    options={FREQUENCIES.map((frequency) => ({
                                        value: frequency,
                                        label: t(
                                            `orders.plans.frequencies.${frequency}`,
                                        ),
                                    }))}
                                />
                                <InputError message={errors.frequency} />
                            </div>

                            <div className={`grid gap-2 ${span.half}`}>
                                <FieldLabel
                                    htmlFor="kits_per_cycle"
                                    help={t('orders.plans.kits_per_cycle_help')}
                                >
                                    {t('orders.plans.kits_per_cycle')}
                                </FieldLabel>
                                <Input
                                    id="kits_per_cycle"
                                    name="kits_per_cycle"
                                    type="number"
                                    min={1}
                                    defaultValue={defaults.kits_per_cycle}
                                    required
                                />
                                <InputError message={errors.kits_per_cycle} />
                            </div>

                            <div className={`grid gap-2 ${span.half}`}>
                                <FieldLabel
                                    htmlFor="advance_amount"
                                    help={t('orders.plans.advance_amount_help')}
                                >
                                    {t('orders.plans.advance_amount')}
                                </FieldLabel>
                                <Input
                                    id="advance_amount"
                                    name="advance_amount"
                                    type="number"
                                    min={0}
                                    defaultValue={defaults.advance_amount}
                                    required
                                />
                                <InputError message={errors.advance_amount} />
                            </div>

                            <div
                                className={`grid gap-2 ${third ? span.third : span.half}`}
                            >
                                <FieldLabel
                                    htmlFor="starts_on"
                                    help={t('orders.plans.starts_on_help')}
                                >
                                    {t('orders.plans.starts_on')}
                                </FieldLabel>
                                <Input
                                    id="starts_on"
                                    name="starts_on"
                                    type="date"
                                    defaultValue={defaults.starts_on}
                                    required
                                />
                                <InputError message={errors.starts_on} />
                            </div>

                            <div
                                className={`grid gap-2 ${third ? span.third : span.half}`}
                            >
                                <FieldLabel
                                    htmlFor="plan-stop"
                                    help={t('orders.plans.stop_help')}
                                >
                                    {t('orders.plans.stop')}
                                </FieldLabel>
                                <SelectField
                                    id="plan-stop"
                                    name="stop"
                                    defaultValue="never"
                                    options={STOPS.map((stop) => ({
                                        value: stop,
                                        label: t(`orders.plans.stops.${stop}`),
                                    }))}
                                />
                            </div>

                            {hasDate && (
                                <div
                                    className={`grid gap-2 fade-in ${span.third}`}
                                >
                                    <FieldLabel
                                        htmlFor="ends_on"
                                        help={t('orders.plans.ends_on_help')}
                                    >
                                        {t('orders.plans.ends_on')}
                                    </FieldLabel>
                                    <Input
                                        id="ends_on"
                                        name="ends_on"
                                        type="date"
                                        required
                                    />
                                    <InputError message={errors.ends_on} />
                                </div>
                            )}

                            {hasCount && (
                                <div
                                    className={`grid gap-2 fade-in ${span.third}`}
                                >
                                    <FieldLabel
                                        htmlFor="cycles_count"
                                        help={t(
                                            'orders.plans.cycles_count_help',
                                        )}
                                    >
                                        {t('orders.plans.cycles_count')}
                                    </FieldLabel>
                                    <Input
                                        id="cycles_count"
                                        name="cycles_count"
                                        type="number"
                                        min={1}
                                        required
                                    />
                                    <InputError message={errors.cycles_count} />
                                </div>
                            )}
                        </div>

                        <p
                            className="rounded-md bg-muted/50 px-3 py-2 text-sm"
                            aria-live="polite"
                        >
                            {preview}
                        </p>

                        <Button variant="success" disabled={processing}>
                            {t('orders.plans.submit')}
                        </Button>
                    </>
                );
            }}
        </Form>
    );
}
