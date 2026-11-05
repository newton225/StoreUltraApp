import { router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderProformaController';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
import SelectField from '@/components/select-field';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Issues, in one click, the proforma of every waiting order of the small businesses of a locality
 * (playbook UI-110 for the step, UI-111 for the confirmation, UI-114 for the folded panel).
 */
export default function OrderProformaBatch({
    localities,
}: {
    localities: { name: string; count: number }[];
}) {
    const { t } = useTranslation();
    const [locality, setLocality] = useState('');
    const count = localities.find((item) => item.name === locality)?.count ?? 0;

    return (
        <CollapsiblePanel
            title={t('orders.proformas.open')}
            defaultOpen={false}
        >
            <div className="max-w-xl space-y-4">
                <p className="text-sm text-muted-foreground">
                    {t('orders.proformas.description')}
                </p>

                {localities.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.proformas.none_available')}
                    </p>
                ) : (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="proforma-locality">
                                {t('orders.proformas.locality')}
                            </Label>
                            <SelectField
                                id="proforma-locality"
                                name="locality"
                                required
                                onChange={setLocality}
                                options={[
                                    {
                                        value: '',
                                        label: t('orders.proformas.choose'),
                                    },
                                    ...localities.map((item) => ({
                                        value: item.name,
                                        label: `${item.name} (${item.count})`,
                                    })),
                                ]}
                            />
                            {locality !== '' && (
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
                            disabled={locality === ''}
                            onConfirm={() =>
                                router.post(
                                    store.url(),
                                    { locality },
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
