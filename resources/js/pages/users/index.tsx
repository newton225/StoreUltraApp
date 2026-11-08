import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/UserController';
import ExportMenu from '@/components/export-menu';
import GuidedTour, { type TourStep } from '@/components/guided-tour';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

type ListedUser = {
    id: number;
    name: string;
    email: string;
    profile: string;
    is_active: boolean;
    invitation_pending: boolean;
};

const USERS_TOUR: TourStep[] = [
    {
        target: 'new',
        title: 'tours.users.new.title',
        body: 'tours.users.new.body',
    },
    {
        target: 'filters',
        title: 'tours.users.filters.title',
        body: 'tours.users.filters.body',
    },
    {
        target: 'table',
        title: 'tours.users.table.title',
        body: 'tours.users.table.body',
    },
];

type Filters = {
    search: string;
    profile: number | null;
    state: string | null;
};

export default function UsersIndex({
    users,
    filters,
    profiles,
    states,
}: {
    users: ListedUser[];
    filters: Filters;
    profiles: { id: number; name: string }[];
    states: string[];
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const filtered =
        filters.search !== '' ||
        filters.profile !== null ||
        filters.state !== null;

    const apply = (patch: Partial<Record<string, string>>): void => {
        const next = {
            search,
            profile: filters.profile === null ? '' : String(filters.profile),
            state: filters.state ?? '',
            ...patch,
        };

        router.get(
            index.url(),
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, replace: true },
        );
    };

    setLayoutProps({
        breadcrumbs: [{ title: t('users.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('users.head')} />
            <GuidedTour tour="users" steps={USERS_TOUR} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('users.title')}
                        description={t('users.description')}
                    />
                    <div className="flex items-center gap-2">
                        <ExportMenu view="users" query={filters} />
                        <Button variant="info" asChild data-tour="new">
                            <Link href={create()}>{t('users.new')}</Link>
                        </Button>
                    </div>
                </div>

                <form
                    data-tour="filters"
                    className="grid gap-3 sm:grid-cols-[minmax(0,26rem)_14rem_14rem]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({});
                    }}
                >
                    <div className="flex gap-2">
                        <Input
                            className="flex-1"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('users.filters.search')}
                            aria-label={t('users.filters.search')}
                            data-test="users-search"
                        />
                        <Button type="submit" variant="secondary">
                            {t('users.filters.apply')}
                        </Button>
                    </div>
                    <SelectField
                        id="filter-profile"
                        searchable
                        name="profile"
                        defaultValue={String(filters.profile ?? '')}
                        onChange={(value) => apply({ profile: value })}
                        options={[
                            {
                                value: '',
                                label: t('users.filters.all_profiles'),
                            },
                            ...profiles.map((profile) => ({
                                value: String(profile.id),
                                label: profile.name,
                            })),
                        ]}
                    />
                    <SelectField
                        id="filter-state"
                        name="state"
                        defaultValue={filters.state ?? ''}
                        onChange={(value) => apply({ state: value })}
                        options={[
                            { value: '', label: t('users.filters.all_states') },
                            ...states.map((state) => ({
                                value: state,
                                label: t(`users.status.${state}`),
                            })),
                        ]}
                    />
                </form>

                {users.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {filtered
                            ? t('users.filters.no_match')
                            : t('users.empty')}
                    </p>
                ) : (
                    <div
                        data-tour="table"
                        className="overflow-x-auto rounded-lg border border-border"
                    >
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('users.columns.name')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('users.columns.email')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('users.columns.profile')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('users.columns.status')}
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2 font-medium">
                                            {user.name}
                                        </td>
                                        <td className="px-4 py-2">
                                            {user.email}
                                        </td>
                                        <td className="px-4 py-2">
                                            {user.profile}
                                        </td>
                                        <td className="px-4 py-2">
                                            {user.invitation_pending &&
                                            user.is_active ? (
                                                <Badge variant="warning">
                                                    {t('users.status.pending')}
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant={
                                                        user.is_active
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {user.is_active
                                                        ? t(
                                                              'users.status.active',
                                                          )
                                                        : t(
                                                              'users.status.inactive',
                                                          )}
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link href={edit(user.id)}>
                                                    {t('users.edit')}
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
