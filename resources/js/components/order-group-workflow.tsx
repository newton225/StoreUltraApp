import { useState } from 'react';
import type { ComponentProps } from 'react';
import { EmbeddedPanelContext } from '@/components/collapsible-panel';
import OrderAdvancePayments from '@/components/order-advance-payments';
import OrderDeliveryBatch from '@/components/order-delivery-batch';
import OrderProformaBatch from '@/components/order-proforma-batch';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Rights = { proforma: boolean; collect: boolean; deliver: boolean };

type Props = {
    rights: Rights;
    /** How many orders sit at each step of their life. */
    counts: Record<string, number>;
    proformaZones: ComponentProps<typeof OrderProformaBatch>['zones'];
    collectZones: ComponentProps<typeof OrderAdvancePayments>['zones'];
    paymentMethods: ComponentProps<typeof OrderAdvancePayments>['methods'];
    collectors: ComponentProps<typeof OrderAdvancePayments>['collectors'];
    currentUserId: number;
    deliveryZones: ComponentProps<typeof OrderDeliveryBatch>['zones'];
    deliveryAgents: ComponentProps<typeof OrderDeliveryBatch>['agents'];
};

/**
 * The group actions, in the order of the life of an order. Each one is named after what is done and is offered for the
 * orders that sit at the step of the same name: "Deliver the kits" for the orders at "Advance received".
 */
const STEPS = ['draft', 'proforma_issued', 'advance_received'] as const;

/**
 * The group actions of the orders page, by step of the life of an order, as the steps of one order are shown on its
 * page (playbook UI-110): pick the step where the orders are and the action that moves them to the next one opens
 * below, for a whole sub-prefecture. A step with no group action is shown but cannot be opened.
 */
export default function OrderGroupWorkflow({
    rights,
    counts,
    proformaZones,
    collectZones,
    paymentMethods,
    collectors,
    currentUserId,
    deliveryZones,
    deliveryAgents,
}: Props) {
    const { t } = useTranslation();
    const [active, setActive] = useState<string | null>(null);

    const actions: Record<string, { allowed: boolean; title: string }> = {
        draft: { allowed: rights.proforma, title: t('orders.proformas.open') },
        proforma_issued: {
            allowed: rights.collect,
            title: t('orders.collect.open'),
        },
        advance_received: {
            allowed: rights.deliver,
            title: t('orders.deliver_batch.open'),
        },
    };

    if (!Object.values(actions).some((action) => action.allowed)) {
        return null;
    }

    return (
        <section
            data-tour="batches"
            className="space-y-3 rounded-lg border border-border p-4"
        >
            <div className="space-y-1">
                <h2 className="text-sm font-semibold">
                    {t('orders.group.title')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t('orders.group.help')}
                </p>
            </div>

            <ol
                aria-label={t('orders.group.title')}
                className="flex flex-wrap gap-2 text-xs"
            >
                {STEPS.map((status) => {
                    const action = actions[status];
                    const openable = action?.allowed === true;
                    const selected = active === status;
                    const label = `${t(`orders.group.steps.${status}`)} · ${counts[status] ?? 0}`;
                    const hint = t('orders.group.at_step', {
                        count: counts[status] ?? 0,
                        step: t(`orders.statuses.${status}`),
                    });

                    return (
                        <li key={status} title={hint}>
                            {openable ? (
                                <button
                                    type="button"
                                    aria-pressed={selected}
                                    onClick={() =>
                                        setActive(selected ? null : status)
                                    }
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-full border px-3 py-1 whitespace-nowrap transition-colors duration-300 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                        selected
                                            ? 'border-foreground bg-foreground font-semibold text-background'
                                            : 'border-border text-foreground hover:bg-muted/60',
                                    )}
                                >
                                    {label}
                                </button>
                            ) : (
                                <span className="flex items-center gap-1.5 rounded-full border border-dashed border-border px-3 py-1 whitespace-nowrap text-muted-foreground">
                                    {label}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>

            {active !== null && actions[active]?.allowed && (
                <div
                    key={active}
                    className="space-y-3 rounded-lg border border-border p-4 fade-in"
                >
                    <div className="space-y-0.5">
                        <h3 className="text-sm font-semibold">
                            {actions[active].title}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {t('orders.group.at_step', {
                                count: counts[active] ?? 0,
                                step: t(`orders.statuses.${active}`),
                            })}
                        </p>
                    </div>
                    <EmbeddedPanelContext.Provider value={true}>
                        {active === 'draft' && (
                            <OrderProformaBatch zones={proformaZones} />
                        )}
                        {active === 'proforma_issued' && (
                            <OrderAdvancePayments
                                zones={collectZones}
                                methods={paymentMethods}
                                collectors={collectors}
                                currentUserId={currentUserId}
                            />
                        )}
                        {active === 'advance_received' && (
                            <OrderDeliveryBatch
                                zones={deliveryZones}
                                agents={deliveryAgents}
                            />
                        )}
                    </EmbeddedPanelContext.Provider>
                </div>
            )}
        </section>
    );
}
