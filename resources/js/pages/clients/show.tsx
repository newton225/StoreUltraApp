import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/ClientController';
import type { ClientOrder, Plan } from '@/components/client-supply';
import ClientSupply from '@/components/client-supply';
import ClientStatusActions from '@/components/client-status-actions';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { StatusBadge } from '@/components/status-badge';

type Client = {
    id: number;
    reference: string | null;
    commercial_number: string | null;
    name: string;
    phone: string;
    status: string;
    zone: { id: number; name: string } | null;
    locality: string | null;
    delivery_address: string | null;
    contact_person: string | null;
    contact_trade: string | null;
    notes: string | null;
    created_by: string | null;
    created_at: string | null;
};

type Props = {
    client: Client;
    canChangeStatus: boolean;
    statuses: string[];
    plans: Plan[];
    orders: ClientOrder[];
    canManageOrders: boolean;
    planDefaults: {
        kits_per_cycle: number;
        advance_amount: number;
        frequency: string;
        starts_on: string;
    } | null;
    kits: { id: number; name: string }[];
};

export default function ShowClient({
    client,
    canChangeStatus,
    statuses,
    plans,
    orders,
    canManageOrders,
    planDefaults,
    kits,
}: Props) {
    const { t } = useTranslation();
    const { date } = useFormat();

    setLayoutProps({
        breadcrumbs: [
            { title: t('clients.title'), href: index() },
            { title: client.name, href: show(client.id) },
        ],
    });

    const details: [string, string | null][] = [
        [t('clients.form.commercial_number'), client.commercial_number],
        [t('clients.form.phone'), client.phone],
        [t('clients.form.zone'), client.zone?.name ?? null],
        [t('clients.form.locality'), client.locality],
        [t('clients.form.delivery_address'), client.delivery_address],
        [t('clients.form.contact_person'), client.contact_person],
        [t('clients.show.contact_trade'), client.contact_trade],
        [t('clients.form.notes'), client.notes],
    ];

    return (
        <>
            <Head title={client.name} />

            <div className="max-w-3xl space-y-8 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <Heading
                            title={client.name}
                            description={client.reference ?? undefined}
                        />
                        <StatusBadge
                            group="clients"
                            status={client.status}
                            variant={
                                client.status === 'active'
                                    ? 'secondary'
                                    : 'outline'
                            }
                        />
                    </div>
                    <Button asChild variant="outline">
                        <Link href={edit(client.id)}>{t('clients.edit')}</Link>
                    </Button>
                </div>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">
                        {t('clients.show.details')}
                    </h2>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        {details.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd>{value || '—'}</dd>
                            </div>
                        ))}
                    </dl>
                    {client.created_at && (
                        <p className="text-xs text-muted-foreground">
                            {t('clients.show.registered', {
                                date: date(client.created_at),
                                author: client.created_by ?? '—',
                            })}
                        </p>
                    )}
                </section>

                {canChangeStatus && (
                    <ClientStatusActions
                        clientId={client.id}
                        clientName={client.name}
                        current={client.status}
                        statuses={statuses}
                    />
                )}

                <ClientSupply
                    clientId={client.id}
                    plans={plans}
                    orders={orders}
                    canManageOrders={canManageOrders}
                    planDefaults={planDefaults}
                    kits={kits}
                />
            </div>
        </>
    );
}
