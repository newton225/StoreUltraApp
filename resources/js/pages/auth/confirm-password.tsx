import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';

export default function ConfirmPassword() {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('access.confirm.title'),
        description: t('access.confirm.description'),
    });

    return (
        <>
            <Head title={t('access.confirm.title')} />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label={t('access.confirm.passkey')}
                loadingLabel={t('access.confirm.passkey_loading')}
                separator={t('access.confirm.passkey_separator')}
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('access.fields.password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder={t(
                                    'access.fields.password_placeholder',
                                )}
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                variant="success"
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {t('access.confirm.submit')}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}
