import { router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderAdvanceController';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
import SelectField from '@/components/select-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type Locality = {
    name: string;
    orders: { id: number; client: string; due: number; paid: number }[];
};

/**
 * Records the step "advance received" for the small businesses of a locality whose advance is fully paid.
 * Only settled orders are offered, all ticked; the office unticks the ones to leave (playbook UI-115).
 */
export default function OrderAdvanceBatch({
    localities,
}: {
    localities: Locality[];
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const [locality, setLocality] = useState('');
    const [unticked, setUnticked] = useState<number[]>([]);
    const orders =
        localities.find((item) => item.name === locality)?.orders ?? [];
    const picked = orders.filter((order) => !unticked.includes(order.id));

    const toggle = (id: number, checked: boolean): void =>
        setUnticked((current) =>
            checked
                ? current.filter((value) => value !== id)
                : [...current, id],
        );

    return (
        <CollapsiblePanel title={t('orders.advances.open')} defaultOpen={false}>
            <div className="max-w-xl space-y-4">
                <p className="text-sm text-muted-foreground">
                    {t('orders.advances.description')}
                </p>

                {localities.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('orders.advances.none_available')}
                    </p>
                ) : (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="advance-locality">
                                {t('orders.advances.locality')}
                            </Label>
                            <SelectField
                                id="advance-locality"
                                name="locality"
                                required
                                onChange={(value) => {
                                    setLocality(value);
                                    setUnticked([]);
                                }}
                                options={[
                                    {
                                        value: '',
                                        label: t('orders.advances.choose'),
                                    },
                                    ...localities.map((item) => ({
                                        value: item.name,
                                        label: `${item.name} (${item.orders.length})`,
                                    })),
                                ]}
                            />
                        </div>

                        {orders.length > 0 && (
                            <div className="space-y-2 fade-in">
                                <div className="flex items-center justify-between text-sm">
                                    <button
                                        type="button"
                                        className="text-primary underline-offset-4 hover:underline"
                                        onClick={() => setUnticked([])}
                                    >
                                        {t('orders.advances.select_all')}
                                    </button>
                                    <span className="text-muted-foreground">
                                        {t('orders.advances.selected', {
                                            count: picked.length,
                                        })}
                                    </span>
                                </div>
                                <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                                    {orders.map((order) => (
                                        <li key={order.id}>
                                            <label className="flex cursor-pointer items-center gap-3 px-4 py-2">
                                                <Checkbox
                                                    checked={
                                                        !unticked.includes(
                                                            order.id,
                                                        )
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggle(
                                                            order.id,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <span className="flex-1 font-medium">
                                                    {order.client}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {t('orders.advances.paid', {
                                                        paid: money(order.paid),
                                                        due: money(order.due),
                                                    })}
                                                </span>
                                            </label>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <ConfirmAction
                            label={t('orders.advances.confirm')}
                            title={t('orders.advances.confirm_title')}
                            description={t('orders.advances.confirm_body', {
                                count: picked.length,
                            })}
                            disabled={picked.length === 0}
                            onConfirm={() =>
                                router.post(
                                    store.url(),
                                    {
                                        locality,
                                        order_ids: picked.map(
                                            (order) => order.id,
                                        ),
                                    },
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
