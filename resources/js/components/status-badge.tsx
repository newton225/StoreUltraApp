import type { ComponentProps } from 'react';
import { Hint } from '@/components/hint';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

type StatusGroup = 'orders' | 'clients' | 'purchases' | 'orders.plans';
type Tone = NonNullable<ComponentProps<typeof Badge>['variant']>;

/**
 * The colour of a status says where the record stands (playbook UI-125): grey = not started or over, blue = under way,
 * amber = waiting for someone or paused, green = done or in force, red = cancelled or refused.
 */
const TONES: Record<StatusGroup, Record<string, Tone>> = {
    orders: {
        draft: 'neutral',
        proforma_issued: 'info',
        advance_received: 'info',
        ordered: 'warning',
        received: 'warning',
        delivered: 'success',
        settled: 'success',
        closed: 'neutral',
        cancelled: 'danger',
    },
    clients: {
        pending: 'warning',
        active: 'success',
        suspended: 'warning',
        ended: 'neutral',
    },
    purchases: {
        draft: 'neutral',
        sent: 'info',
        partially_received: 'warning',
        received: 'success',
        cancelled: 'danger',
    },
    'orders.plans': {
        active: 'success',
        paused: 'warning',
        ended: 'neutral',
    },
};

/**
 * A status as a badge that explains itself: hovering it, or reaching it with the keyboard, says what
 * the status means and what usually comes next. The texts are `<group>.statuses.<status>` for the name
 * and `<group>.status_help.<status>` for the explanation. The colour comes from the status, so it is the same on
 * every screen; the name is always written too (UI-52).
 */
export function StatusBadge({
    group,
    status,
    variant,
    ...props
}: { group: StatusGroup; status: string } & Omit<
    ComponentProps<typeof Badge>,
    'children'
>) {
    const { t } = useTranslation();

    return (
        <Hint label={t(`${group}.status_help.${status}`)}>
            <Badge
                tabIndex={0}
                variant={variant ?? TONES[group][status] ?? 'neutral'}
                {...props}
            >
                {t(`${group}.statuses.${status}`)}
            </Badge>
        </Hint>
    );
}
