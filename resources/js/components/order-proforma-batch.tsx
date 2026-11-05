import { router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderProformaController';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
import SelectField from '@/components/select-field';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Issues, in one click, the proforma of every waiting order of the small businesses of a zone (sub-prefecture)
 * (playbook UI-110 for the step, UI-111 for the confirmation, UI-114 for the folded panel).
 */
export default function OrderProformaBatch({
    zones,
}: {
    zones: { id: number; name: string; count: number }[];
}) {
    const { t } = useTranslation();
    const [zone, setZone] = useState('');
    const count = zones.find((item) => String(item.id) === zone)?.count ?? 0;

    return (
        <CollapsiblePanel
            title={t('orders.proformas.open')}
            defaultOpen={false}
        >
            <div className="max-w-xl space-y-4">
                <p className="text-sm text-muted-foreground">
                    {t('orders.proformas.description')}
                </p>

                {zones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.proformas.none_available')}
                    </p>
                ) : (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="proforma-zone">
                                {t('orders.proformas.zone')}
                            </Label>
                            <SelectField
                                id="proforma-zone"
                                name="zone_id"
                                required
                                onChange={setZone}
                                options={[
                                    {
                                        value: '',
                                        label: t('orders.proformas.choose'),
                                    },
                                    ...zones.map((item) => ({
                                        value: String(item.id),
                                        label: `${item.name} (${item.count})`,
                                    })),
                                ]}
                            />
                            {zone !== '' && (
                                <p className="text-sm text-muted-foreground fade-in">
                                    {t('orders.proformas.pending', { count })}
                                </p>
                            )}
                        </div>

                        <ConfirmAction
                            label={t('orders.proformas.confirm')}
                            title={t('orders.proformas.confirm_title')}
                            description={t('orders.proformas.confirm_body', {
                                count,
                            })}
                            disabled={zone === ''}
                            onConfirm={() =>
                                router.post(
                                    store.url(),
                                    { zone_id: zone },
                                    { preserveScroll: true },
                                )
                            }
                        />
                    </>
                )}
            </div>
        </CollapsiblePanel>
    );
}
