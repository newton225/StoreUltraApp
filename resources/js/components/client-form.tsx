import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { SaveBar } from '@/components/save-bar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ZoneQuickAdd from '@/components/zone-quick-add';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';
import { FieldLabel } from '@/components/field-label';

export type EditableClient = {
    id: number;
    reference: string | null;
    commercial_number: string | null;
    name: string;
    phone: string;
    zone_id: number;
    locality: string | null;
    delivery_address: string | null;
    contact_person: string | null;
    notes: string | null;
    status: string;
};

type Props = {
    formProps: { action: string; method: 'post' };
    zones: { id: number; name: string }[];
    statuses: string[];
    client?: EditableClient;
};

export default function ClientForm({
    formProps,
    zones,
    statuses,
    client,
}: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [zoneList, setZoneList] = useState(zones);
    const [zoneId, setZoneId] = useState(String(client?.zone_id ?? ''));

    return (
        <Form setDefaultsOnSuccess {...formProps} className={formGrid}>
            {({ processing, isDirty, errors: formErrors }) => {
                const errors: Record<string, string | undefined> = formErrors;

                return (
                    <>
                        {/* Identity */}
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="name">
                                {t('clients.form.name')}
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={client?.name ?? ''}
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2 md:col-span-3">
                            <FieldLabel
                                htmlFor="commercial_number"
                                help={t('clients.form.commercial_number_help')}
                            >
                                {t('clients.form.commercial_number')}
                            </FieldLabel>
                            <Input
                                id="commercial_number"
                                name="commercial_number"
                                defaultValue={client?.commercial_number ?? ''}
                                required
                            />
                            <InputError message={errors.commercial_number} />
                        </div>

                        {/* Contact */}
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="phone">
                                {t('clients.form.phone')}
                            </Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                defaultValue={client?.phone ?? ''}
                                required
                            />
                            <InputError message={errors.phone} />
                        </div>

                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="contact_person">
                                {t('clients.form.contact_person')}
                            </Label>
                            <Input
                                id="contact_person"
                                name="contact_person"
                                defaultValue={client?.contact_person ?? ''}
                            />
                            <InputError message={errors.contact_person} />
                        </div>

                        {/* Place */}
                        <div className="grid gap-2 md:col-span-3">
                            <FieldLabel
                                htmlFor="zone_id"
                                help={t('clients.form.zone_help')}
                            >
                                {t('clients.form.zone')}
                            </FieldLabel>
                            <div className="flex items-start gap-2">
                                <div className="flex-1">
                                    <SelectField
                                        key={`${zoneId}-${zoneList.length}`}
                                        id="zone_id"
                                        name="zone_id"
                                        required
                                        defaultValue={zoneId}
                                        onChange={setZoneId}
                                        options={[
                                            {
                                                value: '',
                                                label: t(
                                                    'clients.form.choose_zone',
                                                ),
                                            },
                                            ...zoneList.map((zone) => ({
                                                value: String(zone.id),
                                                label: zone.name,
                                            })),
                                        ]}
                                    />
                                </div>
                                {auth.canManageSettings && (
                                    <ZoneQuickAdd
                                        onAdded={(zone) => {
                                            setZoneList((list) =>
                                                [...list, zone].sort((a, b) =>
                                                    a.name.localeCompare(
                                                        b.name,
                                                    ),
                                                ),
                                            );
                                            setZoneId(String(zone.id));
                                        }}
                                    />
                                )}
                            </div>
                            {zoneList.length === 0 &&
                                auth.canManageSettings && (
                                    <p className="text-sm text-muted-foreground">
                                        {t('clients.form.no_zone_yet')}
                                    </p>
                                )}
                            <InputError message={errors.zone_id} />
                        </div>

                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="locality">
                                {t('clients.form.locality')}
                            </Label>
                            <Input
                                id="locality"
                                name="locality"
                                defaultValue={client?.locality ?? ''}
                            />
                            <InputError message={errors.locality} />
                        </div>

                        {/* Delivery, then notes and status */}
                        <div
                            className={`grid gap-2 ${statuses.length > 0 ? 'md:col-span-4' : 'col-span-full'}`}
                        >
                            <Label htmlFor="delivery_address">
                                {t('clients.form.delivery_address')}
                            </Label>
                            <Input
                                id="delivery_address"
                                name="delivery_address"
                                defaultValue={client?.delivery_address ?? ''}
                            />
                            <InputError message={errors.delivery_address} />
                        </div>

                        {statuses.length > 0 && (
                            <div className="grid gap-2 md:col-span-2">
                                <FieldLabel
                                    htmlFor="status"
                                    help={t('clients.form.status_help')}
                                >
                                    {t('clients.form.status')}
                                </FieldLabel>
                                <SelectField
                                    id="status"
                                    name="status"
                                    defaultValue={client?.status ?? 'active'}
                                    options={statuses.map((status) => ({
                                        value: status,
                                        label: t(`clients.statuses.${status}`),
                                    }))}
                                />
                                <InputError message={errors.status} />
                            </div>
                        )}

                        <div className="col-span-full grid gap-2">
                            <Label htmlFor="notes">
                                {t('clients.form.notes')}
                            </Label>
                            <Input
                                id="notes"
                                name="notes"
                                defaultValue={client?.notes ?? ''}
                            />
                            <InputError message={errors.notes} />
                        </div>

                        <SaveBar
                            dirty={isDirty}
                            processing={processing}
                            isNew={!client}
                        >
                            {t('clients.form.submit')}
                        </SaveBar>
                    </>
                );
            }}
        </Form>
    );
}
