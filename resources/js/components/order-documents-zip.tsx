import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/OrderDocumentArchiveController';
import CollapsiblePanel from '@/components/collapsible-panel';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import WeekSelect from '@/components/week-select';
import { useTranslation } from '@/hooks/use-translation';

type Count = { ready: number; missing: number };

export type ArchiveChoices = {
    weeks: string[];
    zones: {
        id: number;
        name: string;
        counts: Record<string, { proforma: Count; invoice: Count }>;
    }[];
};

/**
 * Downloads, in one zip, the proformas or the invoices already issued for the orders of a zone (sub-prefecture)
 * and a delivery week. Nothing is issued here (playbook UI-114 for the folded panel). The weeks are the ones that
 * have orders, and each sub-prefecture says how many documents are ready and how many are still to generate.
 */
export default function OrderDocumentsZip({
    choices,
    week,
}: {
    choices: ArchiveChoices;
    week: string | null;
}) {
    const { t } = useTranslation();
    const [kind, setKind] = useState<'proforma' | 'invoice'>('proforma');
    const [zone, setZone] = useState('');
    const [chosenWeek, setChosenWeek] = useState(
        week !== null && choices.weeks.includes(week)
            ? week
            : (choices.weeks[0] ?? ''),
    );

    const countOf = (id: number): Count =>
        choices.zones.find((item) => item.id === id)?.counts[chosenWeek]?.[
            kind
        ] ?? { ready: 0, missing: 0 };
    const ready = zone !== '' && countOf(Number(zone)).ready > 0;

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
                            onChange={(value) =>
                                setKind(
                                    value === 'invoice'
                                        ? 'invoice'
                                        : 'proforma',
                                )
                            }
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
                        <WeekSelect
                            id="archive-week"
                            weeks={choices.weeks}
                            value={chosenWeek}
                            onChange={setChosenWeek}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="archive-zone">
                            {t('orders.archive.zone')}
                        </Label>
                        <SelectField
                            id="archive-zone"
                            searchable
                            name="zone"
                            prompt
                            onChange={setZone}
                            options={[
                                {
                                    value: '',
                                    label: t('orders.archive.choose'),
                                },
                                ...choices.zones.map((item) => {
                                    const count = countOf(item.id);

                                    return {
                                        value: String(item.id),
                                        label: t(
                                            count.missing > 0
                                                ? 'orders.archive.zone_missing'
                                                : 'orders.archive.zone_ready',
                                            {
                                                name: item.name,
                                                ready: count.ready,
                                                missing: count.missing,
                                            },
                                        ),
                                    };
                                }),
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
