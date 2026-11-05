import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/ZoneController';
import Heading from '@/components/heading';
import ZoneForm from '@/components/zone-form';
import { useTranslation } from '@/hooks/use-translation';

export default function CreateZone() {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('catalog.zones.title'), href: index() },
            { title: t('catalog.zones.create_title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('catalog.zones.create_title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading title={t('catalog.zones.create_title')} />
                <ZoneForm formProps={store.form()} />
            </div>
        </>
    );
}
