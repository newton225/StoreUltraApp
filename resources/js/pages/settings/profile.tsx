import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { Link } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveBar } from '@/components/save-bar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
import { send } from '@/routes/verification';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('navigation.breadcrumbs.profile'), href: edit() },
        ],
    });

    return (
        <>
            <Head title={t('account.profile.head')} />

            <h1 className="sr-only">{t('account.profile.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('account.profile.title')}
                    description={t('account.profile.description')}
                />

                <Form
                    setDefaultsOnSuccess
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, isDirty, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('account.profile.name')}
                                </Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t(
                                        'account.profile.name_placeholder',
                                    )}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('account.profile.email')}
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder={t(
                                        'account.profile.email_placeholder',
                                    )}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            {t('account.profile.unverified')}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                {t('account.profile.resend')}
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                {t('account.profile.link_sent')}
                                            </div>
                                        )}
                                    </div>
                                )}

                            <SaveBar
                                dirty={isDirty}
                                processing={processing}
                                data-test="update-profile-button"
                            >
                                {t('common.actions.save')}
                            </SaveBar>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
