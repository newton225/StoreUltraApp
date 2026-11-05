import { Form, Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/heading';
import PriceFields from '@/components/price-fields';
import type { ShopOption } from '@/components/product-fields';
import ProductFields from '@/components/product-fields';
import { SaveBar } from '@/components/save-bar';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';

type Props = { shops: ShopOption[]; today: string };

export default function CreateProduct({ shops, today }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('catalog.products.title'), href: index() },
            { title: t('catalog.products.create_title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('catalog.products.create_title')} />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    title={t('catalog.products.create_title')}
                    description={t('catalog.products.create_description')}
                />

                <Form
                    setDefaultsOnSuccess
                    {...store.form()}
                    className={formGrid}
                >
                    {({ processing, isDirty, errors: formErrors }) => {
                        const errors: Record<string, string | undefined> =
                            formErrors;

                        return (
                            <>
                                <ProductFields shops={shops} errors={errors} />
                                <PriceFields
                                    hideDate
                                    today={today}
                                    errors={errors}
                                />
                                <SaveBar
                                    dirty={isDirty}
                                    processing={processing}
                                    isNew={true}
                                >
                                    {t('catalog.products.form.create_submit')}
                                </SaveBar>
                            </>
                        );
                    }}
                </Form>
            </div>
        </>
    );
}
