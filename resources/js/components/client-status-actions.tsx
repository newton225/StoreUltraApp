import { router } from '@inertiajs/react';
import { status as statusRoute } from '@/actions/App/Http/Controllers/ClientController';
import ConfirmAction from '@/components/confirm-action';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Changes only the status of a client (activate, deactivate, end) with a confirmation that says what changes.
 * It does not go through the edit form, so a client with an incomplete form can still be deactivated
 * (playbook UI-111).
 */
export default function ClientStatusActions({
    clientId,
    clientName,
    current,
    statuses,
}: {
    clientId: number;
    clientName: string;
    current: string;
    statuses: string[];
}) {
    const { t } = useTranslation();

    return (
        <section className="space-y-2">
            <h2 className="text-base font-semibold">
                {t('clients.status_actions.title')}
            </h2>
            <p className="text-sm text-muted-foreground">
                {t('clients.status_actions.help')}
            </p>
            <div className="flex flex-wrap gap-2">
                {statuses
                    .filter((status) => status !== current)
                    .map((status) => (
                        <ConfirmAction
                            key={status}
                            label={t(`clients.status_actions.${status}`)}
                            title={t('clients.status_actions.confirm_title', {
                                name: clientName,
                                status: t(`clients.statuses.${status}`),
                            })}
                            description={t(
                                `clients.status_actions.body.${status}`,
                            )}
                            variant={
                                status === 'active' ? 'success' : 'outline'
                            }
                            destructive={status === 'ended'}
                            onConfirm={() =>
                                router.patch(
                                    statusRoute.url(clientId),
                                    { status },
                                    { preserveScroll: true },
                                )
                            }
                        />
                    ))}
            </div>
        </section>
    );
}
