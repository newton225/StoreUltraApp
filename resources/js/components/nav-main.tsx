import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup, NavTone } from '@/types';

/**
 * One colour per group of the menu, taken from the same palette as the charts, so the menu reads at a
 * glance and stays harmonious. The icon carries the colour; the label stays in the text colour.
 */
const ICON_TONES: Record<NavTone, string> = {
    dashboard: 'text-nav-dashboard',
    sales: 'text-nav-sales',
    supply: 'text-nav-supply',
    finance: 'text-nav-finance',
    admin: 'text-nav-admin',
    config: 'text-nav-config',
};

/** The address of a menu entry, whether it is written as a string or as a route object. */
const hrefOf = (href: NavGroup['items'][number]['href']): string =>
    typeof href === 'string' ? href : href.url;

type Pill = { x: number; y: number; width: number; height: number };

/**
 * The selection of the menu is one pill that glides from a section to the next (transform only), like a
 * switcher, instead of vanishing here and appearing there. It is measured on the active entry, follows a
 * resize or a collapse of the sidebar, does not glide in on the first display and hides when the current
 * page is not in the menu.
 */
export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { url } = usePage();
    const container = useRef<HTMLDivElement>(null);
    // Where the pill last was (it stays there, hidden, while the page is not in the menu) and whether it shows.
    const [place, setPlace] = useState<Pill | null>(null);
    const [visible, setVisible] = useState(false);
    const [ready, setReady] = useState(false);
    // The entry just clicked: the pill goes there at once, without waiting for the next page to load.
    const [pending, setPending] = useState<string | null>(null);

    // The new page is there (or the move failed): the pill follows the real current page again.
    useLayoutEffect(() => {
        setPending(null);
    }, [url]);

    useEffect(() => {
        const stops = [
            router.on('httpException', () => setPending(null)),
            router.on('networkError', () => setPending(null)),
        ];

        return () => stops.forEach((stop) => stop());
    }, []);

    useLayoutEffect(() => {
        const root = container.current;

        if (!root) {
            return;
        }

        const measure = () => {
            const active =
                (pending
                    ? root.querySelector<HTMLElement>(
                          `[data-sidebar="menu-button"][data-nav-href="${pending}"]`,
                      )
                    : null) ??
                root.querySelector<HTMLElement>(
                    '[data-sidebar="menu-button"][data-active="true"]',
                );

            if (!active) {
                setVisible(false);

                return;
            }

            const box = root.getBoundingClientRect();
            const rect = active.getBoundingClientRect();

            const next = {
                x: rect.left - box.left,
                y: rect.top - box.top,
                width: rect.width,
                height: rect.height,
            };

            setVisible(true);
            setPlace((now) =>
                now &&
                now.x === next.x &&
                now.y === next.y &&
                now.width === next.width &&
                now.height === next.height
                    ? now
                    : next,
            );
        };

        measure();

        const observer = new ResizeObserver(measure);

        observer.observe(root);

        return () => observer.disconnect();
    }, [url, pending]);

    // The very first placement never glides: gliding is allowed one frame after the pill has a place.
    const placed = place !== null;

    useEffect(() => {
        if (!placed) {
            return;
        }

        const frame = requestAnimationFrame(() => setReady(true));

        return () => cancelAnimationFrame(frame);
    }, [placed]);

    return (
        <div
            ref={container}
            className="relative isolate flex min-h-0 flex-col gap-2"
        >
            <span
                aria-hidden="true"
                data-ready={ready}
                className="nav-pill pointer-events-none absolute top-0 left-0 -z-10 rounded-md bg-sidebar-accent"
                style={{
                    width: place?.width,
                    height: place?.height,
                    transform: place
                        ? `translate(${place.x}px, ${place.y}px)`
                        : undefined,
                    opacity: visible ? 1 : 0,
                }}
            />
            {groups
                .filter((group) => group.items.length > 0)
                .map((group) => (
                    <SidebarGroup
                        key={group.label ?? 'main'}
                        className="px-2 py-1"
                    >
                        {group.label && (
                            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        )}
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                        data-nav-href={hrefOf(item.href)}
                                        className="data-[active=true]:bg-transparent"
                                    >
                                        <Link
                                            href={item.href}
                                            prefetch
                                            onClick={(event) => {
                                                // Ctrl/Cmd/Shift/Alt or another button opens elsewhere: this page does not move.
                                                if (
                                                    event.button === 0 &&
                                                    !event.metaKey &&
                                                    !event.ctrlKey &&
                                                    !event.shiftKey &&
                                                    !event.altKey
                                                ) {
                                                    setPending(
                                                        hrefOf(item.href),
                                                    );
                                                }
                                            }}
                                        >
                                            {item.icon && (
                                                <item.icon
                                                    className={
                                                        group.tone
                                                            ? ICON_TONES[
                                                                  group.tone
                                                              ]
                                                            : undefined
                                                    }
                                                />
                                            )}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
        </div>
    );
}
