import { ChevronDown } from 'lucide-react';
import { createContext, useContext, useId, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Inside a screen that already gives the block its own title and opens it (the group actions of the orders page, one
 * per step of the life of an order), the folding header is not wanted: the content is shown as it is.
 */
export const EmbeddedPanelContext = createContext(false);

/**
 * A block that can be folded up by its title, for a form that sits above a list (playbook UI-106 and
 * UI-114). The content is only hidden, not removed: what has been typed is kept when the panel is folded
 * and opened again.
 */
export default function CollapsiblePanel({
    title,
    defaultOpen = true,
    children,
}: {
    title: string;
    defaultOpen?: boolean;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const contentId = useId();
    const embedded = useContext(EmbeddedPanelContext);

    if (embedded) {
        return <div className="fade-in">{children}</div>;
    }

    return (
        <section className="rounded-lg border border-border">
            <button
                type="button"
                data-test="collapsible-trigger"
                aria-expanded={open}
                aria-controls={contentId}
                onClick={() => setOpen((current) => !current)}
                className="flex w-full items-center justify-between gap-3 rounded-lg p-4 text-left text-sm font-medium transition-colors hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                {title}
                <ChevronDown
                    aria-hidden
                    className={cn(
                        'size-4 text-muted-foreground transition-transform duration-200',
                        open && 'rotate-180',
                    )}
                />
            </button>
            <div
                id={contentId}
                hidden={!open}
                className="border-t border-border p-4 fade-in"
            >
                {children}
            </div>
        </section>
    );
}
