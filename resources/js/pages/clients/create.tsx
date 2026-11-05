import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/ClientController';
import ClientForm from '@/components/client-form';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    zones: { id: number; name: string }[];
    statuses: string[];
};

export default function CreateClient({ zones, statuses }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('clients.title'), href: index() },
            { title: t('clients.create.title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('clients.create.head')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title={t('clients.create.title')}
                    description={t('clients.create.description')}
                />
                <ClientForm
                    formProps={store.form()}
                    zones={zones}
                    statuses={statuses}
                />
            </div>
        </>
    );
}
