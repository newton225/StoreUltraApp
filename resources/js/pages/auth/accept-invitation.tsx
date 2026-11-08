import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { accept } from '@/actions/App/Http/Controllers/InvitationController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

/** Where a person invited by the owner chooses their own password (playbook SEC-165). */
export default function AcceptInvitation({
    token,
    email,
    passwordRules,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('access.invitation.title'),
        description: t('access.invitation.description'),
    });

    return (
        <>
            <Head title={t('access.invitation.title')} />

            <Form
                {...accept.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">
                                {t('access.fields.email')}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="username"
                                value={email}
                                className="mt-1 block w-full"
                                readOnly
                            />
                            <InputError
                                message={errors.email}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('access.fields.password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                className="mt-1 block w-full"
                                autoFocus
                                placeholder={t(
                                    'access.fields.password_placeholder',
                                )}
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('access.fields.password_confirmation')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                className="mt-1 block w-full"
                                placeholder={t(
                                    'access.fields.password_confirmation_placeholder',
                                )}
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                                className="mt-2"
                            />
                        </div>

                        <Button
                            variant="success"
                            type="submit"
                            className="mt-4 w-full"
                            disabled={processing}
                            data-test="accept-invitation-button"
                        >
                            {processing && <Spinner />}
                            {t('access.invitation.submit')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}
