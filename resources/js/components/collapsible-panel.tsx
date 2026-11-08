import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

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
