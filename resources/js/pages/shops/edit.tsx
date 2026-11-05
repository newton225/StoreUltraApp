import { Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    update,
} from '@/actions/App/Http/Controllers/ShopController';
import Heading from '@/components/heading';
import type { EditableShop } from '@/components/shop-form';
import ShopForm from '@/components/shop-form';
import { useTranslation } from '@/hooks/use-translation';

export default function EditShop({ shop }: { shop: EditableShop }) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('catalog.shops.title'), href: index() },
            { title: shop.name, href: edit(shop.id) },
        ],
    });

    return (
        <>
            <Head title={t('catalog.shops.edit_title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading title={t('catalog.shops.edit_title')} />
                <ShopForm formProps={update.form(shop.id)} shop={shop} />
            </div>
        </>
    );
}
