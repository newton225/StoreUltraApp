import { Head, setLayoutProps } from '@inertiajs/react';
import {
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/ClientController';
import type { EditableClient } from '@/components/client-form';
import ClientForm from '@/components/client-form';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    client: EditableClient;
    zones: { id: number; name: string }[];
    statuses: string[];
};

export default function EditClient({ client, zones, statuses }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('clients.title'), href: index() },
            { title: client.name, href: show(client.id) },
        ],
    });

    return (
        <>
            <Head title={t('clients.edit_page.head')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title={t('clients.edit_page.title')}
                    description={client.reference ?? undefined}
                />
                <ClientForm
                    formProps={update.form(client.id)}
                    zones={zones}
                    statuses={statuses}
                    client={client}
                />
            </div>
        </>
    );
}
