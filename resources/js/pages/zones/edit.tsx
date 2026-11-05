import { Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    update,
} from '@/actions/App/Http/Controllers/ZoneController';
import Heading from '@/components/heading';
import type { EditableZone } from '@/components/zone-form';
import ZoneForm from '@/components/zone-form';
import { useTranslation } from '@/hooks/use-translation';

export default function EditZone({ zone }: { zone: EditableZone }) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('catalog.zones.title'), href: index() },
            { title: zone.name, href: edit(zone.id) },
        ],
    });

    return (
        <>
            <Head title={t('catalog.zones.edit_title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading title={t('catalog.zones.edit_title')} />
                <ZoneForm formProps={update.form(zone.id)} zone={zone} />
            </div>
        </>
    );
}
