import type { ComponentProps } from 'react';
import { Hint } from '@/components/hint';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

type StatusGroup = 'orders' | 'clients' | 'purchases' | 'orders.plans';

/**
 * A status as a badge that explains itself: hovering it, or reaching it with the keyboard, says what
 * the status means and what usually comes next. The texts are `<group>.statuses.<status>` for the name
 * and `<group>.status_help.<status>` for the explanation.
 */
export function StatusBadge({
    group,
    status,
    ...props
}: { group: StatusGroup; status: string } & Omit<
    ComponentProps<typeof Badge>,
    'children'
>) {
    const { t } = useTranslation();

    return (
        <Hint label={t(`${group}.status_help.${status}`)}>
            <Badge tabIndex={0} {...props}>
                {t(`${group}.statuses.${status}`)}
            </Badge>
        </Hint>
    );
}
