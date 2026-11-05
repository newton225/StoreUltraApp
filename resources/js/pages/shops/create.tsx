import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/ShopController';
import Heading from '@/components/heading';
import ShopForm from '@/components/shop-form';
import { useTranslation } from '@/hooks/use-translation';

export default function CreateShop() {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('catalog.shops.title'), href: index() },
            { title: t('catalog.shops.create_title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('catalog.shops.create_title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading title={t('catalog.shops.create_title')} />
                <ShopForm formProps={store.form()} />
            </div>
        </>
    );
}
