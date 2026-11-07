import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/ExpenseController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { SaveBar } from '@/components/save-bar';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { formGrid } from '@/lib/form-layout';
import { useTranslation } from '@/hooks/use-translation';
import { newIdempotencyKey } from '@/lib/idempotency';
import { FieldLabel } from '@/components/field-label';

type Named = { id: number; name: string };
type Labelled = { id: number; label: string };

type Props = {
    types: Named[];
    zones: Named[];
    shops: Named[];
    orders: Labelled[];
    deliveries: Labelled[];
    today: string;
};

const SCOPES = ['week', 'zone', 'shop', 'order', 'delivery'] as const;

export default function CreateExpense({
    types,
    zones,
    shops,
    orders,
    deliveries,
    today,
}: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const canCash = auth.permissions.includes('manage_cash');
    const [scope, setScope] = useState<(typeof SCOPES)[number]>('week');
    const [idempotencyKey] = useState(newIdempotencyKey);

    setLayoutProps({
        breadcrumbs: [
            { title: t('expenses.title'), href: index() },
            { title: t('expenses.form.title'), href: create() },
        ],
    });

    const choose = (placeholder: string) => ({
        value: '',
        label: placeholder,
    });

    return (
        <>
            <Head title={t('expenses.form.title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title={t('expenses.form.title')}
                    description={t('expenses.form.description')}
                />

                <Form
                    setDefaultsOnSuccess
                    {...store.form()}
                    className={formGrid}
                >
                    {({ processing, isDirty, errors: formErrors }) => {
                        const errors: Record<string, string | undefined> =
                            formErrors;

                        return (
                            <>
                                <input
                                    type="hidden"
                                    name="idempotency_key"
                                    value={idempotencyKey}
                                />
                                <>
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="expense_type_id">
                                            {t('expenses.form.type')}
                                        </Label>
                                        <SelectField
                                            id="expense_type_id"
                                            name="expense_type_id"
                                            required
                                            options={[
                                                choose(
                                                    t(
                                                        'expenses.form.choose_type',
                                                    ),
                                                ),
                                                ...types.map((type) => ({
                                                    value: String(type.id),
                                                    label: type.name,
                                                })),
                                            ]}
                                        />
                                        <InputError
                                            message={errors.expense_type_id}
                                        />
                                    </div>
                                    <div className="grid gap-2 md:col-span-2">
                                        <FieldLabel
                                            htmlFor="amount"
                                            help={t(
                                                'expenses.form.amount_help',
                                            )}
                                        >
                                            {t('expenses.form.amount')}
                                        </FieldLabel>
                                        <Input
                                            id="amount"
                                            name="amount"
                                            type="number"
                                            min={1}
                                            step={1}
                                            required
                                        />
                                        <InputError message={errors.amount} />
                                    </div>
                                </>

                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="incurred_on">
                                        {t('expenses.form.date')}
                                    </Label>
                                    <Input
                                        id="incurred_on"
                                        name="incurred_on"
                                        type="date"
                                        max={today}
                                        defaultValue={today}
                                        required
                                    />
                                    <InputError message={errors.incurred_on} />
                                </div>

                                <div className="grid gap-2 md:col-span-2">
                                    <FieldLabel
                                        htmlFor="scope"
                                        help={t('expenses.form.scope_help')}
                                    >
                                        {t('expenses.form.scope')}
                                    </FieldLabel>
                                    <SelectField
                                        id="scope"
                                        name="scope"
                                        defaultValue={scope}
                                        onChange={(value) =>
                                            setScope(
                                                value as (typeof SCOPES)[number],
                                            )
                                        }
                                        options={SCOPES.map((value) => ({
                                            value,
                                            label: t(
                                                `expenses.scopes.${value}`,
                                            ),
                                        }))}
                                    />
                                    <InputError message={errors.scope} />
                                </div>

                                {scope === 'order' && (
                                    <div className="grid gap-2 md:col-span-4">
                                        <Label htmlFor="order_id">
                                            {t('expenses.form.order')}
                                        </Label>
                                        <SelectField
                                            id="order_id"
                                            name="order_id"
                                            options={[
                                                choose(
                                                    t('expenses.form.choose'),
                                                ),
                                                ...orders.map((order) => ({
                                                    value: String(order.id),
                                                    label: order.label,
                                                })),
                                            ]}
                                        />
                                        <InputError message={errors.order_id} />
                                    </div>
                                )}

                                {scope === 'delivery' && (
                                    <div className="grid gap-2 md:col-span-4">
                                        <Label htmlFor="delivery_id">
                                            {t('expenses.form.delivery')}
                                        </Label>
                                        <SelectField
                                            id="delivery_id"
                                            name="delivery_id"
                                            options={[
                                                choose(
                                                    t('expenses.form.choose'),
                                                ),
                                                ...deliveries.map(
                                                    (delivery) => ({
                                                        value: String(
                                                            delivery.id,
                                                        ),
                                                        label: delivery.label,
                                                    }),
                                                ),
                                            ]}
                                        />
                                        <InputError
                                            message={errors.delivery_id}
                                        />
                                    </div>
                                )}

                                {scope === 'zone' && (
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="zone_id">
                                            {t('expenses.form.zone')}
                                        </Label>
                                        <SelectField
                                            id="zone_id"
                                            name="zone_id"
                                            options={[
                                                choose(
                                                    t('expenses.form.choose'),
                                                ),
                                                ...zones.map((zone) => ({
                                                    value: String(zone.id),
                                                    label: zone.name,
                                                })),
                                            ]}
                                        />
                                        <InputError message={errors.zone_id} />
                                    </div>
                                )}

                                {scope === 'shop' && (
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="shop_id">
                                            {t('expenses.form.shop')}
                                        </Label>
                                        <SelectField
                                            id="shop_id"
                                            name="shop_id"
                                            options={[
                                                choose(
                                                    t('expenses.form.choose'),
                                                ),
                                                ...shops.map((shop) => ({
                                                    value: String(shop.id),
                                                    label: shop.name,
                                                })),
                                            ]}
                                        />
                                        <InputError message={errors.shop_id} />
                                    </div>
                                )}

                                {(scope === 'week' ||
                                    scope === 'zone' ||
                                    scope === 'shop') && (
                                    <div
                                        className={`grid gap-2 ${scope === 'week' ? 'md:col-span-4' : 'md:col-span-2'}`}
                                    >
                                        <Label htmlFor="week">
                                            {t('expenses.form.week')}
                                        </Label>
                                        <Input
                                            id="week"
                                            name="week"
                                            type="date"
                                            defaultValue={today}
                                            required
                                        />
                                        <InputError message={errors.week} />
                                    </div>
                                )}

                                {canCash && (
                                    <div className="flex items-start gap-3 md:col-span-3">
                                        <input
                                            id="paid_from_cash"
                                            name="paid_from_cash"
                                            type="checkbox"
                                            value="1"
                                            className="mt-1 size-4 accent-primary"
                                        />
                                        <FieldLabel
                                            htmlFor="paid_from_cash"
                                            help={t(
                                                'expenses.form.paid_from_cash_help',
                                            )}
                                        >
                                            {t('expenses.form.paid_from_cash')}
                                        </FieldLabel>
                                    </div>
                                )}

                                <div className="grid gap-2 md:col-span-3">
                                    <Label htmlFor="notes">
                                        {t('expenses.form.notes')}
                                    </Label>
                                    <Textarea
                                        rows={3}
                                        id="notes"
                                        name="notes"
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="grid gap-2 md:col-span-3">
                                    <Label htmlFor="proof">
                                        {t('expenses.form.proof')}
                                    </Label>
                                    <Input
                                        id="proof"
                                        name="proof"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                    />
                                    <InputError message={errors.proof} />
                                </div>

                                <SaveBar
                                    dirty={isDirty}
                                    processing={processing}
                                    isNew={true}
                                >
                                    {t('expenses.form.submit')}
                                </SaveBar>
                            </>
                        );
                    }}
                </Form>
            </div>
        </>
    );
}
