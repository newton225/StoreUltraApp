// Components
import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('access.verify.title'),
        description: t('access.verify.description'),
    });

    return (
        <>
            <Head title={t('access.verify.title')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {t('access.verify.link_sent')}
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {t('access.verify.resend')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('navigation.log_out')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}
