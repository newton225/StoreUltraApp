import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/ProfileController';
import Heading from '@/components/heading';
import ProfileForm from '@/components/profile-form';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    permissions: string[];
    actions: Record<string, string[]>;
    template: {
        name: string;
        restricted_to_zones: boolean;
        permissions: string[];
    } | null;
};

export default function CreateProfile({
    permissions,
    actions,
    template,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('profiles.title'), href: index() },
            { title: t('profiles.create_title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('profiles.create_title')} />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    title={t('profiles.create_title')}
                    description={t('profiles.create_description')}
                />

                {template && (
                    <Alert>
                        <AlertDescription>
                            {t('profiles.template_notice', {
                                name: template.name,
                            })}
                        </AlertDescription>
                    </Alert>
                )}

                <ProfileForm
                    formProps={store.form()}
                    permissions={permissions}
                    actions={actions}
                    template={template}
                />
            </div>
        </>
    );
}
