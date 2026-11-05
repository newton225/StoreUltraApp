import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

export type NavTone =
    | 'dashboard'
    | 'sales'
    | 'supply'
    | 'finance'
    | 'admin'
    | 'config';

export type NavGroup = {
    label?: string;
    /** The colour of the icons of the group (see the `--nav-*` tokens). */
    tone?: NavTone;
    items: NavItem[];
};
