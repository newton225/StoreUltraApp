import { Link } from '@inertiajs/react';
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

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
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
                                    >
                                        <Link href={item.href} prefetch>
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
        </>
    );
}
