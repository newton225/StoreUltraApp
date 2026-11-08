import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    edit,
    index,
    invitationLink,
    invite,
    update,
} from '@/actions/App/Http/Controllers/UserController';
import ConfirmAction from '@/components/confirm-action';
import Heading from '@/components/heading';
import type { EditableUser, ZoneOption } from '@/components/user-form';
import UserForm from '@/components/user-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    editedUser: EditableUser;
    profiles: { id: number; name: string; restricted_to_zones: boolean }[];
    zones: ZoneOption[];
};

export default function EditUser({
    editedUser,
    profiles,
    zones,
}: Props) {
    const { t } = useTranslation();
    const page = usePage();
    const isSelf = page.props.auth.user.id === editedUser.id;
    // The link is flashed once by the server when the owner asks for it: it is never kept in the page.
    const link = (page.flash as { invitation_link?: string } | undefined)
        ?.invitation_link;
    const [sending, setSending] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: t('users.title'), href: index() },
            { title: editedUser.name, href: edit(editedUser.id) },
        ],
    });

    const sendMail = (): void => {
        router.post(
            invite(editedUser.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
            },
        );
    };

    const copyLink = async (): Promise<void> => {
        if (!link) {
            return;
        }

        try {
            await navigator.clipboard.writeText(link);
            toast.success(t('users.invitation.copied'));
        } catch {
            // The link stays selectable in the field: the person can copy it by hand.
        }
    };

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
                    user={editedUser}
                    isSelf={isSelf}
                    submitLabel={t('users.form.submit')}
                />

                {!isSelf && (
                    <section
                        className="space-y-4 rounded-lg border border-border p-4"
                        data-test="invitation-panel"
                    >
                        <div className="space-y-1">
                            <h2 className="text-base font-semibold">
                                {t('users.invitation.title')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {editedUser.invitation_pending
                                    ? t('users.invitation.pending')
                                    : t('users.invitation.done')}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                variant="info"
                                disabled={sending}
                                onClick={sendMail}
                                data-test="invitation-send"
                            >
                                {sending && <Spinner />}
                                {t('users.invitation.send')}
                            </Button>
                            <ConfirmAction
                                label={t('users.invitation.show_link')}
                                title={t('users.invitation.show_link_title')}
                                description={t(
                                    'users.invitation.show_link_description',
                                )}
                                variant="infoOutline"
                                onConfirm={() =>
                                    router.post(
                                        invitationLink(editedUser.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {t('users.invitation.send_help')}
                        </p>

                        {link && (
                            <div className="grid gap-2">
                                <Label htmlFor="invitation-link">
                                    {t('users.invitation.link_label')}
                                </Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="invitation-link"
                                        readOnly
                                        value={link}
                                        onFocus={(event) =>
                                            event.currentTarget.select()
                                        }
                                        data-test="invitation-link"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={copyLink}
                                    >
                                        {t('users.invitation.copy')}
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {t('users.invitation.link_help')}
                                </p>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
