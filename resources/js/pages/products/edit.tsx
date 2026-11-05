import { Form, Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    update,
} from '@/actions/App/Http/Controllers/ProductController';
import { store as storePrice } from '@/actions/App/Http/Controllers/ProductPriceController';
import Heading from '@/components/heading';
import PriceFields from '@/components/price-fields';
import type { EditableProduct, ShopOption } from '@/components/product-fields';
import ProductFields from '@/components/product-fields';
import { Badge } from '@/components/ui/badge';
import { SaveBar } from '@/components/save-bar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';

type Price = {
    id: number;
    purchase_price: number;
    sale_price: number | null;
    effective_from: string;
};

type Props = {
    product: EditableProduct;
    shops: ShopOption[];
    prices: Price[];
    today: string;
};

export default function EditProduct({ product, shops, prices, today }: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();

    setLayoutProps({
        breadcrumbs: [
            { title: t('catalog.products.title'), href: index() },
            { title: product.name, href: edit(product.id) },
        ],
    });

    const currentId = prices.find(
        (price) => price.effective_from.slice(0, 10) <= today,
    )?.id;

    return (
        <>
            <Head title={t('catalog.products.edit_title')} />

            <div className="max-w-4xl space-y-10 p-4">
                <section className="max-w-4xl space-y-6">
                    <Heading title={t('catalog.products.edit_title')} />

                    <Form
                        setDefaultsOnSuccess
                        {...update.form(product.id)}
                        className={formGrid}
                    >
                        {({ processing, isDirty, errors: formErrors }) => (
                            <>
                                <ProductFields
                                    product={product}
                                    shops={shops}
                                    errors={formErrors}
                                />
                                <SaveBar
                                    dirty={isDirty}
                                    processing={processing}
                                >
                                    {t('catalog.products.form.submit')}
                                </SaveBar>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('catalog.prices.history_title')}
                        description={t('catalog.prices.history_description')}
                    />

                    {prices.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('catalog.prices.history_empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            {t(
                                                'catalog.prices.columns.effective_from',
                                            )}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t(
                                                'catalog.prices.columns.purchase_price',
                                            )}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t(
                                                'catalog.prices.columns.sale_price',
                                            )}
                                        </th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {prices.map((price) => (
                                        <tr
                                            key={price.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2">
                                                {date(price.effective_from)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {money(price.purchase_price)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {price.sale_price !== null
                                                    ? money(price.sale_price)
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {price.id === currentId && (
                                                    <Badge variant="secondary">
                                                        {t(
                                                            'catalog.prices.current',
                                                        )}
                                                    </Badge>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="max-w-4xl space-y-6">
                    <Heading
                        variant="small"
                        title={t('catalog.prices.add_title')}
                    />

                    <Form
                        setDefaultsOnSuccess
                        {...storePrice.form(product.id)}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className={formGrid}
                    >
                        {({ processing, isDirty, errors: formErrors }) => (
                            <>
                                <PriceFields
                                    today={today}
                                    errors={formErrors}
                                />
                                <SaveBar
                                    dirty={isDirty}
                                    processing={processing}
                                    isNew
                                >
                                    {t('catalog.prices.add_submit')}
                                </SaveBar>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}
