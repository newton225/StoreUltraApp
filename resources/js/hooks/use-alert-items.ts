import type { InertiaLinkProps } from '@inertiajs/react';
import { index as attachmentsIndex } from '@/actions/App/Http/Controllers/AttachmentController';
import { index as clientsIndex } from '@/actions/App/Http/Controllers/ClientController';
import { index as ordersIndex } from '@/actions/App/Http/Controllers/OrderController';
import { arrears } from '@/actions/App/Http/Controllers/PaymentController';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import type { Alerts } from '@/types/alerts';

export type AlertItem = {
    key: string;
    text: string;
    href: NonNullable<InertiaLinkProps['href']>;
    linkLabel: string;
};

const ATTACHMENT_KINDS = ['missing', 'expiring', 'expired'] as const;

/**
 * The alerts that are on, each with its sentence and the screen where it is dealt with.
 */
export function useAlertItems(alerts: Alerts | null | undefined): AlertItem[] {
    const { t } = useTranslation();
    const { money } = useFormat();
    const items: AlertItem[] = [];

    if (!alerts) {
        return items;
    }

    if (alerts.late_advances.count > 0) {
        items.push({
            key: 'late',
            text: t('dashboard.alerts.late_advances', {
                count: alerts.late_advances.count,
                amount: money(alerts.late_advances.amount),
            }),
            href: arrears(),
            linkLabel: t('dashboard.alerts.see_arrears'),
        });
    }

    if (alerts.blocked_orders > 0) {
        items.push({
            key: 'blocked',
            text: t('dashboard.alerts.blocked_orders', {
                count: alerts.blocked_orders,
                days: alerts.blocked_after_days,
            }),
            href: ordersIndex(),
            linkLabel: t('dashboard.alerts.see_orders'),
        });
    }

    if (alerts.client_cap?.reached) {
        items.push({
            key: 'cap',
            text: t('dashboard.alerts.client_cap', {
                used: alerts.client_cap.used,
                cap: alerts.client_cap.cap,
            }),
            href: clientsIndex(),
            linkLabel: t('dashboard.alerts.see_clients'),
        });
    }

    for (const kind of ATTACHMENT_KINDS) {
        const count = alerts.attachments?.[kind] ?? 0;

        if (count > 0) {
            items.push({
                key: `attachments-${kind}`,
                text: t(`dashboard.alerts.attachments_${kind}`, { count }),
                href: attachmentsIndex(),
                linkLabel: t('dashboard.alerts.see_attachments'),
            });
        }
    }

    return items;
}
