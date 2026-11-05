import { useState } from 'react';
import { HelpHint } from '@/components/hint';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

export type Summary = {
    purchase: number | null;
    sale: number | null;
    margin: number | null;
    complete: boolean;
    missing_purchase_prices: number[];
};

type Props = {
    summary: Summary;
    kitsPerClient: number;
    defaultClients: number;
    productNames: Record<number, string>;
    /** Purchase cost and margin are the profit: shown only to who may see it. */
    seesCosts?: boolean;
};

export default function KitSummary({
    summary,
    kitsPerClient,
    defaultClients,
    productNames,
    seesCosts = true,
}: Props) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const [clients, setClients] = useState(String(defaultClients));

    const times = (amount: number | null, factor: number): string =>
        amount === null ? '—' : money(amount * factor);

    const clientCount = Math.max(0, Number.parseInt(clients, 10) || 0);

    const rows: { label: string; factor: number }[] = [
        { label: t('kits.summary.per_kit'), factor: 1 },
        {
            label: t('kits.summary.per_client', { kits: kitsPerClient }),
            factor: kitsPerClient,
        },
        {
            label: t('kits.summary.for_clients', { clients: clientCount }),
            factor: kitsPerClient * clientCount,
        },
    ];

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-left text-sm">
                    <thead className="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2" />
                            {seesCosts && (
                                <th className="px-4 py-2 text-right font-medium">
                                    <span className="inline-flex items-center justify-end gap-1.5">
                                        {t('kits.summary.purchase')}
                                        <HelpHint
                                            label={t(
                                                'kits.summary.purchase_help',
                                            )}
                                        />
                                    </span>
                                </th>
                            )}
                            <th className="px-4 py-2 text-right font-medium">
                                {t('kits.summary.sale')}
                            </th>
                            {seesCosts && (
                                <th className="px-4 py-2 text-right font-medium">
                                    <span className="inline-flex items-center justify-end gap-1.5">
                                        {t('kits.summary.margin')}
                                        <HelpHint
                                            label={t(
                                                'kits.summary.margin_help',
                                            )}
                                        />
                                    </span>
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.label}
                                className="border-t border-border"
                            >
                                <td className="px-4 py-2 font-medium">
                                    {row.label}
                                </td>
                                {seesCosts && (
                                    <td className="px-4 py-2 text-right">
                                        {times(summary.purchase, row.factor)}
                                    </td>
                                )}
                                <td className="px-4 py-2 text-right">
                                    {times(summary.sale, row.factor)}
                                </td>
                                {seesCosts && (
                                    <td className="px-4 py-2 text-right">
                                        {times(summary.margin, row.factor)}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="flex items-center gap-3">
                <Label htmlFor="clients-count" className="text-sm">
                    {t('kits.summary.clients')}
                </Label>
                <Input
                    id="clients-count"
                    type="number"
                    min={0}
                    className="w-28"
                    value={clients}
                    onChange={(event) => setClients(event.target.value)}
                />
            </div>

            {summary.missing_purchase_prices.length > 0 && (
                <p className="text-sm text-destructive">
                    {t('kits.summary.missing', {
                        products: summary.missing_purchase_prices
                            .map((id) => productNames[id] ?? `#${id}`)
                            .join(', '),
                    })}
                </p>
            )}
        </div>
    );
}
