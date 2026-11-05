import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import {
    create,
    edit,
    index,
    toggleRight,
} from '@/actions/App/Http/Controllers/ProfileController';
import Heading from '@/components/heading';
import { HelpHint } from '@/components/hint';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from '@/hooks/use-translation';

type ListedProfile = {
    id: number;
    name: string;
    is_system: boolean;
    restricted_to_zones: boolean;
    permissions: string[];
    users_count: number;
};

type Props = {
    profiles: ListedProfile[];
    permissions: string[];
};

export default function ProfilesIndex({ profiles, permissions }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('profiles.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('profiles.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('profiles.title')}
                        description={t('profiles.description')}
                    />
                    <Button variant="info" asChild>
                        <Link href={create()}>{t('profiles.new')}</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    {t('profiles.columns.name')}
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    {t('profiles.columns.rights')}
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    {t('profiles.columns.users')}
                                </th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {profiles.map((profile) => {
                                const everything =
                                    profile.permissions.length ===
                                    permissions.length;

                                return (
                                    <tr
                                        key={profile.id}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {profile.name}
                                            </div>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                <Badge
                                                    variant={
                                                        profile.is_system
                                                            ? 'neutral'
                                                            : 'info'
                                                    }
                                                >
                                                    {t(
                                                        profile.is_system
                                                            ? 'profiles.kinds.system'
                                                            : 'profiles.kinds.custom',
                                                    )}
                                                </Badge>
                                                {profile.restricted_to_zones && (
                                                    <Badge variant="warning">
                                                        {t(
                                                            'profiles.restricted_badge',
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {everything ? (
                                                <span className="text-muted-foreground">
                                                    {t('profiles.all_rights')}
                                                </span>
                                            ) : profile.permissions.length ===
                                              0 ? (
                                                <span className="text-muted-foreground">
                                                    {t('profiles.no_rights')}
                                                </span>
                                            ) : (
                                                <ul className="flex flex-wrap gap-1.5">
                                                    {profile.permissions.map(
                                                        (permission) => (
                                                            <li
                                                                key={permission}
                                                            >
                                                                <Badge variant="outline">
                                                                    {t(
                                                                        `profiles.permissions.${permission}`,
                                                                    )}
                                                                </Badge>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {t('profiles.users_count', {
                                                count: profile.users_count,
                                            })}
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link href={edit(profile.id)}>
                                                    {t(
                                                        profile.is_system
                                                            ? 'profiles.view'
                                                            : 'profiles.edit',
                                                    )}
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={create({
                                                        query: {
                                                            from: profile.id,
                                                        },
                                                    })}
                                                >
                                                    {t('profiles.duplicate')}
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title={t('profiles.matrix_title')}
                        description={t('profiles.matrix_description')}
                    />
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('profiles.matrix_right')}
                                    </th>
                                    {profiles.map((profile) => (
                                        <th
                                            key={profile.id}
                                            className="px-4 py-2 text-center font-medium"
                                        >
                                            {profile.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {permissions.map((permission) => (
                                    <tr
                                        key={permission}
                                        className="border-t border-border"
                                    >
                                        <th
                                            scope="row"
                                            className="px-4 py-2 font-normal"
                                        >
                                            <span className="flex items-center gap-1.5">
                                                {t(
                                                    `profiles.permissions.${permission}`,
                                                )}
                                                <HelpHint
                                                    label={t(
                                                        `profiles.permissions.${permission}_help`,
                                                    )}
                                                />
                                            </span>
                                        </th>
                                        {profiles.map((profile) => (
                                            <td
                                                key={profile.id}
                                                className="px-4 py-2 text-center"
                                            >
                                                <RightCell
                                                    profile={profile}
                                                    right={permission}
                                                    on={profile.permissions.includes(
                                                        permission,
                                                    )}
                                                    label={t(
                                                        `profiles.permissions.${permission}`,
                                                    )}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                                <tr className="border-t border-border">
                                    <th
                                        scope="row"
                                        className="px-4 py-2 font-normal"
                                    >
                                        {t('profiles.restricted_row')}
                                    </th>
                                    {profiles.map((profile) => (
                                        <td
                                            key={profile.id}
                                            className="px-4 py-2 text-center"
                                        >
                                            <RightCell
                                                profile={profile}
                                                right="restricted_to_zones"
                                                on={profile.restricted_to_zones}
                                                label={t(
                                                    'profiles.restricted_row',
                                                )}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

/**
 * One cell of the table of rights. A custom profile gets a checkbox saved on the spot (the page updates
 * at once and goes back if the server refuses); a system profile only shows the mark.
 */
function RightCell({
    profile,
    right,
    on,
    label,
}: {
    profile: ListedProfile;
    right: string;
    on: boolean;
    label: string;
}) {
    const { t } = useTranslation();

    if (profile.is_system) {
        return <Mark on={on} />;
    }

    const toggle = (granted: boolean): void => {
        router
            .optimistic<Props>((props) => ({
                profiles: props.profiles.map((listed) =>
                    listed.id !== profile.id
                        ? listed
                        : right === 'restricted_to_zones'
                          ? { ...listed, restricted_to_zones: granted }
                          : {
                                ...listed,
                                permissions: granted
                                    ? [...listed.permissions, right]
                                    : listed.permissions.filter(
                                          (held) => held !== right,
                                      ),
                            },
                ),
            }))
            .patch(
                toggleRight.url(profile.id),
                { right, granted },
                { preserveScroll: true },
            );
    };

    return (
        <Checkbox
            checked={on}
            onCheckedChange={(value) => toggle(value === true)}
            aria-label={`${profile.name} : ${label} (${t(on ? 'profiles.granted' : 'profiles.not_granted')})`}
        />
    );
}

/** A right granted or not, in an icon and in text for screen readers (never colour alone). */
function Mark({ on }: { on: boolean }) {
    const { t } = useTranslation();

    return on ? (
        <span className="inline-flex text-success">
            <Check className="size-4" aria-hidden="true" />
            <span className="sr-only">{t('profiles.granted')}</span>
        </span>
    ) : (
        <span className="inline-flex text-muted-foreground">
            <Minus className="size-4" aria-hidden="true" />
            <span className="sr-only">{t('profiles.not_granted')}</span>
        </span>
    );
}
