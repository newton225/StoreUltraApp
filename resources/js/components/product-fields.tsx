import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export type ShopOption = { id: number; name: string };

export type EditableProduct = {
    id: number;
    name: string;
    unit: string;
    shop_id: number | null;
    archived_at: string | null;
};

type Props = {
    product?: EditableProduct;
    shops: ShopOption[];
    errors: Record<string, string | undefined>;
};

export default function ProductFields({ product, shops, errors }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <div className="grid gap-2 md:col-span-2">
                <Label htmlFor="name">{t('catalog.products.form.name')}</Label>
                <Input
                    id="name"
                    name="name"
                    defaultValue={product?.name}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2 md:col-span-2">
                <Label htmlFor="unit">{t('catalog.products.form.unit')}</Label>
                <Input
                    id="unit"
                    name="unit"
                    defaultValue={product?.unit ?? 'u'}
                    required
                />
                <InputError message={errors.unit} />
            </div>

            <div className="grid gap-2 md:col-span-2">
                <Label htmlFor="shop_id">
                    {t('catalog.products.form.shop')}
                </Label>
                <SelectField
                    id="shop_id"
                    searchable
                    name="shop_id"
                    required
                    defaultValue={String(product?.shop_id ?? '')}
                    options={[
                        { value: '', label: t('catalog.none') },
                        ...shops.map((shop) => ({
                            value: String(shop.id),
                            label: shop.name,
                        })),
                    ]}
                />
                <InputError message={errors.shop_id} />
            </div>
        </>
    );
}
