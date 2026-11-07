import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { update } from '@/actions/App/Http/Controllers/Settings/BusinessSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { SaveBar } from '@/components/save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { edit } from '@/routes/parameters';
import { formGrid } from '@/lib/form-layout';
import { FieldLabel } from '@/components/field-label';

type Definition = {
    type: 'integer' | 'boolean' | 'string';
    options: string[] | null;
    min: number | null;
};

type Props = {
    values: Record<string, string | number | boolean | null>;
    definitions: Record<string, Definition>;
};

export default function ParametersEdit({ values, definitions }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('parameters.head'), href: edit() }],
    });

    const optionLabel = (key: string, option: string): string => {
        if (key === 'week_starts_on') {
            return t(`parameters.days.${option}`);
        }

        if (key === 'fne_mode') {
            return t(`parameters.fne_modes.${option}`);
        }

        if (key === 'expense_allocation') {
            return t(`parameters.allocations.${option}`);
        }

        return t(`parameters.frequencies.${option}`);
    };

    return (
        <>
            <Head title={t('parameters.head')} />

            <div className="max-w-5xl space-y-8 p-4">
                <Heading
                    title={t('parameters.title')}
                    description={t('parameters.description')}
                />

                <Form
                    setDefaultsOnSuccess
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-8"
                >
                    {({ processing, isDirty, errors }) => {
                        const fieldWith = (key: string, span: string) => {
                            const definition = definitions[key];
                            const value = values[key];

                            if (definition.type === 'boolean') {
                                return (
                                    <div
                                        key={key}
                                        className="grid gap-2 self-end pb-2 md:col-span-2"
                                    >
                                        <div className="flex items-center space-x-3">
                                            <input
                                                type="hidden"
                                                name={key}
                                                value="0"
                                            />
                                            <Checkbox
                                                id={key}
                                                name={key}
                                                value="1"
                                                defaultChecked={value === true}
                                            />
                                            <FieldLabel
                                                htmlFor={key}
                                                help={t(
                                                    `parameters.fields.${key}_help`,
                                                )}
                                            >
                                                {t(`parameters.fields.${key}`)}
                                            </FieldLabel>
                                        </div>
                                        <InputError message={errors[key]} />
                                    </div>
                                );
                            }

                            return (
                                <div key={key} className={`grid gap-2 ${span}`}>
                                    <FieldLabel
                                        htmlFor={key}
                                        help={t(
                                            `parameters.fields.${key}_help`,
                                        )}
                                    >
                                        {t(`parameters.fields.${key}`)}
                                    </FieldLabel>
                                    {definition.options ? (
                                        <SelectField
                                            id={key}
                                            name={key}
                                            defaultValue={String(value)}
                                            options={definition.options.map(
                                                (option) => ({
                                                    value: option,
                                                    label: optionLabel(
                                                        key,
                                                        option,
                                                    ),
                                                }),
                                            )}
                                        />
                                    ) : (
                                        <Input
                                            id={key}
                                            name={key}
                                            type={
                                                definition.type === 'integer'
                                                    ? 'number'
                                                    : 'text'
                                            }
                                            min={definition.min ?? undefined}
                                            defaultValue={
                                                value === null
                                                    ? ''
                                                    : String(value)
                                            }
                                        />
                                    )}
                                    <InputError message={errors[key]} />
                                </div>
                            );
                        };

                        // Three fields per row, or two wide ones to fill a row of two.
                        const field = (key: string) =>
                            fieldWith(key, 'md:col-span-2');

                        return (
                            <>
                                <section className={formGrid}>
                                    <h2 className="col-span-full text-base font-semibold">
                                        {t('parameters.sections.supply')}
                                    </h2>
                                    {[
                                        'kits_per_client',
                                        'advance_amount',
                                        'client_cap',
                                        'order_lead_days',
                                        'advance_required',
                                        'delivery_signature_required',
                                    ].map(field)}
                                </section>

                                <details
                                    open={Object.keys(errors).length > 0}
                                    className="group rounded-lg border border-border"
                                >
                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-3 p-4">
                                        <span>
                                            <span className="block text-base font-semibold">
                                                {t(
                                                    'parameters.sections.advanced',
                                                )}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {t(
                                                    'parameters.sections.advanced_help',
                                                )}
                                            </span>
                                        </span>
                                        <ChevronDown className="size-4 shrink-0 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <div className="space-y-8 border-t border-border p-4">
                                        <section className={formGrid}>
                                            <h2 className="col-span-full text-base font-semibold">
                                                {t(
                                                    'parameters.sections.rhythm',
                                                )}
                                            </h2>
                                            {[
                                                'advance_frequency',
                                                'delivery_frequency',
                                                'invoice_period',
                                                'week_starts_on',
                                                'proforma_validity_days',
                                                'fne_mode',
                                            ].map(field)}
                                        </section>

                                        <section className={formGrid}>
                                            <h2 className="col-span-full text-base font-semibold">
                                                {t(
                                                    'parameters.sections.alerts',
                                                )}
                                            </h2>
                                            {[
                                                'expense_allocation',
                                                'attachment_alert_days',
                                                'blocked_order_days',
                                            ].map(field)}
                                        </section>

                                        <section className={formGrid}>
                                            <h2 className="col-span-full text-base font-semibold">
                                                {t(
                                                    'parameters.sections.numbering',
                                                )}
                                            </h2>
                                            {[
                                                'document_prefix_receipt',
                                                'document_prefix_purchase_order',
                                                'document_prefix_proforma',
                                                'document_prefix_delivery_note',
                                                'document_prefix_invoice',
                                                'document_prefix_statement',
                                            ].map(field)}
                                        </section>
                                    </div>
                                </details>

                                <SaveBar
                                    dirty={isDirty}
                                    processing={processing}
                                >
                                    {t('parameters.save')}
                                </SaveBar>
                            </>
                        );
                    }}
                </Form>
            </div>
        </>
    );
}
