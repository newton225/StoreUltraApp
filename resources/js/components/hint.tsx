import { CircleHelp } from 'lucide-react';
import type { ComponentProps, ReactElement } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type Side = ComponentProps<typeof TooltipContent>['side'];

/**
 * A tooltip on any element. It opens on hover and on keyboard focus, closes with Escape, and is never
 * the only place an essential piece of information is given (a touch screen has no hover).
 */
export function Hint({
    label,
    side,
    children,
}: {
    label: string;
    side?: Side;
    children: ReactElement;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent side={side}>{label}</TooltipContent>
        </Tooltip>
    );
}

/**
 * The small help mark beside a label or a figure: it explains a business term or says how a number is
 * worked out. It can be reached with the keyboard and is read out with its text.
 */
export function HelpHint({ label }: { label: string }) {
    return (
        <Hint label={label}>
            <button
                type="button"
                aria-label={label}
                className="inline-flex rounded-full text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <CircleHelp className="size-3.5" />
            </button>
        </Hint>
    );
}
