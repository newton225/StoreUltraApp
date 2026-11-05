import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/OrderDocumentArchiveController';
import CollapsiblePanel from '@/components/collapsible-panel';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Downloads, in one zip, the proformas or the invoices already issued for the orders of a zone (sub-prefecture)
 * and a delivery week. Nothing is issued here (playbook UI-114 for the folded panel).
 */
export default function OrderDocumentsZip({
    zones,
    week,
}: {
    zones: { id: number; name: string }[];
    week: string | null;
}) {
    const { t } = useTranslation();
    const [kind, setKind] = useState('proforma');
    const [zone, setZone] = useState('');
    const [chosenWeek, setChosenWeek] = useState(week ?? '');
    const ready = zone !== '' && chosenWeek !== '';

    return (
        <CollapsiblePanel title={t('orders.archive.open')} defaultOpen={false}>
            <div className="max-w-xl space-y-4">
                <p className="text-sm text-muted-foreground">
                    {t('orders.archive.description')}
                </p>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="archive-kind">
                            {t('orders.archive.kind')}
                        </Label>
                        <SelectField
                            id="archive-kind"
                            name="type"
                            defaultValue="proforma"
                            onChange={setKind}
                            options={[
                                {
                                    value: 'proforma',
                                    label: t('orders.archive.proforma'),
                                },
                                {
                                    value: 'invoice',
                                    label: t('orders.archive.invoice'),
                                },
                            ]}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="archive-week">
                            {t('orders.archive.week')}
                        </Label>
                        <Input
                            id="archive-week"
                            type="date"
                            value={chosenWeek}
                            onChange={(event) =>
                                setChosenWeek(event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="archive-zone">
                            {t('orders.archive.zone')}
                        </Label>
                        <SelectField
                            id="archive-zone"
                            name="zone"
                            prompt
                            onChange={setZone}
                            options={[
                                {
                                    value: '',
                                    label: t('orders.archive.choose'),
                                },
                                ...zones.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                        />
                    </div>
                </div>

                {ready ? (
                    <Button variant="info" asChild>
                        <a
                            href={show.url({
                                query: {
                                    type: kind,
                                    zone,
                                    week: chosenWeek,
                                },
                            })}
                        >
                            {t('orders.archive.download')}
                        </a>
                    </Button>
                ) : (
                    <Button variant="info" disabled>
                        {t('orders.archive.download')}
                    </Button>
                )}
            </div>
        </CollapsiblePanel>
    );
}
