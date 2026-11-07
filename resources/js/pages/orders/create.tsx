import { Form, Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/OrderController';
import GuidedTour, { type TourStep } from '@/components/guided-tour';
import Heading from '@/components/heading';
import type { Candidate } from '@/components/order-batch-form';
import OrderBatchForm from '@/components/order-batch-form';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { SaveBar } from '@/components/save-bar';
import TabLinks from '@/components/tab-links';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';
import { FieldLabel } from '@/components/field-label';

type Props = {
    clients: { id: number; name: string; reference: string | null }[];
    kits: { id: number; name: string }[];
    defaults: {
        kits_count: number;
        advance_due: number;
        delivery_week: string;
    };
    mode: 'single' | 'zone';
    zones: { id: number; name: string; count: number }[];
    zone: string;
    batchWeek: string | null;
    candidates: Candidate[];
};

const ORDER_CREATE_TOUR: TourStep[] = [
    {
        target: 'modes',
        title: 'tours.order_create.modes.title',
        body: 'tours.order_create.modes.body',
    },
    {
        target: 'form_single',
        title: 'tours.order_create.form_single.title',
        body: 'tours.order_create.form_single.body',
    },
    {
        target: 'form_batch',
        title: 'tours.order_create.form_batch.title',
        body: 'tours.order_create.form_batch.body',
    },
];

export default function CreateOrder({
    clients,
    kits,
    defaults,
    mode,
    zones,
    zone,
    batchWeek,
    candidates,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('orders.title'), href: index() },
            { title: t('orders.create.title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('orders.create.head')} />
            <GuidedTour tour="order-create" steps={ORDER_CREATE_TOUR} />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    title={t('orders.create.title')}
                    description={t('orders.create.description')}
                />

                <div data-tour="modes">
                    <TabLinks
                        label={t('orders.batch.mode_label')}
                        current={mode}
                        tabs={(['single', 'zone'] as const).map((key) => ({
                            key,
                            label: t(`orders.batch.modes.${key}`),
                            href: create.url(
                                key === 'zone' ? { query: { mode: key } } : {},
                            ),
                        }))}
                    />
                </div>

                {mode === 'zone' ? (
                    <OrderBatchForm
                        zones={zones}
                        zone={zone}
                        batchWeek={batchWeek}
                        candidates={candidates}
                        kits={kits}
                        defaults={defaults}
                    />
                ) : (
                    <Form
                        setDefaultsOnSuccess
                        {...store.form()}
                        data-tour="form_single"
                        className={formGrid}
                    >
                        {({ processing, isDirty, errors: formErrors }) => {
                            const errors: Record<string, string | undefined> =
                                formErrors;

                            return (
                                <>
                                    <div className="grid gap-2 md:col-span-3">
                                        <Label htmlFor="client_id">
                                            {t('orders.create.client')}
                                        </Label>
                                        <SelectField
                                            id="client_id"
                                            name="client_id"
                                            required
                                            options={[
                                                {
                                                    value: '',
                                                    label: t(
                                                        'orders.create.choose_client',
                                                    ),
                                                },
                                                ...clients.map((client) => ({
                                                    value: String(client.id),
                                                    label: `${client.name} (${client.reference ?? ''})`,
                                                })),
                                            ]}
                                        />
                                        <InputError
                                            message={errors.client_id}
                                        />
                                    </div>

                                    <div className="grid gap-2 md:col-span-3">
                                        <FieldLabel
                                            htmlFor="kit_id"
                                            help={t('orders.create.kit_help')}
                                        >
                                            {t('orders.create.kit')}
                                        </FieldLabel>
                                        <SelectField
                                            id="kit_id"
                                            name="kit_id"
                                            required
                                            options={[
                                                {
                                                    value: '',
                                                    label: t(
                                                        'orders.create.choose_kit',
                                                    ),
                                                },
                                                ...kits.map((kit) => ({
                                                    value: String(kit.id),
                                                    label: kit.name,
                                                })),
                                            ]}
                                        />
                                        <InputError message={errors.kit_id} />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <FieldLabel
                                            htmlFor="kits_count"
                                            help={t(
                                                'orders.create.kits_count_help',
                                            )}
                                        >
                                            {t('orders.create.kits_count')}
                                        </FieldLabel>
                                        <Input
                                            id="kits_count"
                                            name="kits_count"
                                            type="number"
                                            min={1}
                                            defaultValue={defaults.kits_count}
                                            required
                                        />
                                        <InputError
                                            message={errors.kits_count}
                                        />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <FieldLabel
                                            htmlFor="delivery_week"
                                            help={t(
                                                'orders.create.delivery_week_help',
                                            )}
                                        >
                                            {t('orders.create.delivery_week')}
                                        </FieldLabel>
                                        <Input
                                            id="delivery_week"
                                            name="delivery_week"
                                            type="date"
                                            defaultValue={
                                                defaults.delivery_week
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.delivery_week}
                                        />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <FieldLabel
                                            htmlFor="advance_due"
                                            help={t(
                                                'orders.create.advance_due_help',
                                            )}
                                        >
                                            {t('orders.create.advance_due')}
                                        </FieldLabel>
                                        <Input
                                            id="advance_due"
                                            name="advance_due"
                                            type="number"
                                            min={0}
                                            defaultValue={defaults.advance_due}
                                            required
                                        />
                                        <InputError
                                            message={errors.advance_due}
                                        />
                                    </div>

                                    <div className="col-span-full grid gap-2">
                                        <Label htmlFor="notes">
                                            {t('orders.create.notes')}
                                        </Label>
                                        <Textarea
                                            rows={3}
                                            id="notes"
                                            name="notes"
                                        />
                                        <InputError message={errors.notes} />
                                    </div>

                                    <SaveBar
                                        dirty={isDirty}
                                        processing={processing}
                                        isNew={true}
                                    >
                                        {t('orders.create.submit')}
                                    </SaveBar>
                                </>
                            );
                        }}
                    </Form>
                )}
            </div>
        </>
    );
}
