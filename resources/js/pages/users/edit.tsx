import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import {
    edit,
    index,
    update,
} from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import type { EditableUser, ZoneOption } from '@/components/user-form';
import UserForm from '@/components/user-form';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    editedUser: EditableUser;
    profiles: { id: number; name: string; restricted_to_zones: boolean }[];
    zones: ZoneOption[];
    passwordRules: string;
};

export default function EditUser({
    editedUser,
    profiles,
    zones,
    passwordRules,
}: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    setLayoutProps({
        breadcrumbs: [
            { title: t('users.title'), href: index() },
            { title: editedUser.name, href: edit(editedUser.id) },
        ],
    });

    return (
        <>
            <Head title={t('users.edit_page.head')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title={t('users.edit_page.title')}
                    description={t('users.edit_page.description')}
                />
                <UserForm
                    formProps={update.form(editedUser.id)}
                    profiles={profiles}
                    zones={zones}
                    passwordRules={passwordRules}
                    user={editedUser}
                    isSelf={auth.user.id === editedUser.id}
                    submitLabel={t('users.form.submit')}
                />
            </div>
        </>
    );
}
