import type { ComponentProps } from 'react';
import { HelpHint } from '@/components/hint';
import { Label } from '@/components/ui/label';

/**
 * The label of a field, with a help mark beside it when the field needs explaining: what it means, how
 * it is used, what happens with it. The help is a tooltip that opens on hover and on keyboard focus.
 */
export function FieldLabel({
    help,
    children,
    ...props
}: ComponentProps<typeof Label> & { help?: string }) {
    if (!help) {
        return <Label {...props}>{children}</Label>;
    }

    return (
        <div className="flex items-center gap-1.5">
            <Label {...props}>{children}</Label>
            <HelpHint label={help} />
        </div>
    );
}
