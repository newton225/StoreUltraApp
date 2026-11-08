import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import UserForm from '@/components/user-form';
import { useTranslation } from '@/hooks/use-translation';

import type { ZoneOption } from '@/components/user-form';

type Props = {
    profiles: { id: number; name: string; restricted_to_zones: boolean }[];
    zones: ZoneOption[];
};

export default function CreateUser({ profiles, zones }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('users.title'), href: index() },
            { title: t('users.create.title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('users.create.head')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title={t('users.create.title')}
                    description={t('users.create.description')}
                />
                <UserForm
                    formProps={store.form()}
                    profiles={profiles}
                    zones={zones}
                    submitLabel={t('users.create.submit')}
                />
            </div>
        </>
    );
}
