import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    create,
    destroy,
    edit,
    index,
    update,
} from '@/actions/App/Http/Controllers/ProfileController';
import Heading from '@/components/heading';
import type { EditableProfile } from '@/components/profile-form';
import ProfileForm from '@/components/profile-form';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    profile: EditableProfile & { name: string; users_count: number };
    permissions: string[];
};

export default function EditProfile({ profile, permissions }: Props) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: t('profiles.title'), href: index() },
            { title: profile.name, href: edit(profile.id) },
        ],
    });

    const remove = (): void => {
        router.delete(destroy.url(profile.id), {
            onFinish: () => setConfirming(false),
        });
    };

    return (
        <>
            <Head title={profile.name} />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    title={profile.name}
                    description={t(
                        profile.is_system
                            ? 'profiles.system_notice'
                            : 'profiles.edit_title',
                    )}
                />

                {profile.is_system && (
                    <Alert>
                        <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
                            {t('profiles.system_notice')}
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={create({
                                        query: { from: profile.id },
                                    })}
                                >
                                    {t('profiles.from_this')}
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                <ProfileForm
                    formProps={update.form(profile.id)}
                    permissions={permissions}
                    profile={profile}
                    readOnly={profile.is_system}
                />

                {!profile.is_system && (
                    <section className="space-y-2 border-t border-border pt-6">
                        {profile.users_count > 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('profiles.in_use_help')}
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={profile.users_count > 0}
                            onClick={() => setConfirming(true)}
                        >
                            {t('profiles.delete')}
                        </Button>
                    </section>
                )}
            </div>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('profiles.delete')}</DialogTitle>
                        <DialogDescription>
                            {t('profiles.delete_confirm', {
                                name: profile.name,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            autoFocus
                            onClick={() => setConfirming(false)}
                        >
                            {t('common.actions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={remove}
                        >
                            {t('profiles.delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
