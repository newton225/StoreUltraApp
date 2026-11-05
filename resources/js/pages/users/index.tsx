import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/UserController';
import ExportMenu from '@/components/export-menu';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type ListedUser = {
    id: number;
    name: string;
    email: string;
    profile: string;
    is_active: boolean;
};

export default function UsersIndex({ users }: { users: ListedUser[] }) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('users.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('users.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('users.title')}
                        description={t('users.description')}
                    />
                    <div className="flex items-center gap-2">
                        <ExportMenu view="users" />
                        <Button asChild>
                            <Link href={create()}>{t('users.new')}</Link>
                        </Button>
                    </div>
                </div>

                {users.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('users.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
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
                                            <Badge
                                                variant={
                                                    user.is_active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {user.is_active
                                                    ? t('users.status.active')
                                                    : t(
                                                          'users.status.inactive',
                                                      )}
                                            </Badge>
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
