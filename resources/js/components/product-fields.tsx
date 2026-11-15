import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export type ShopOption = { id: number; name: string };

export type EditableProduct = {
    id: number;
    name: string;
    unit: string;
    shop_id: number | null;
    pack_size: number;
    sellable: boolean;
    bought_whole: boolean;
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
            <div className="grid gap-2 md:col-span-3">
                <Label htmlFor="name">{t('catalog.products.form.name')}</Label>
                <Input
                    id="name"
                    name="name"
                    defaultValue={product?.name}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2 md:col-span-3">
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

            <div className="grid gap-2 md:col-span-3">
                <Label htmlFor="unit">{t('catalog.products.form.unit')}</Label>
                <Input
                    id="unit"
                    name="unit"
                    defaultValue={product?.unit ?? 'u'}
                    required
                />
                <InputError message={errors.unit} />
            </div>

            <div className="grid gap-2 md:col-span-3">
                <FieldLabel
                    htmlFor="pack_size"
                    help={t('catalog.products.form.pack_size_help')}
                >
                    {t('catalog.products.form.pack_size')}
                </FieldLabel>
                <Input
                    id="pack_size"
                    name="pack_size"
                    type="number"
                    min={1}
                    step={1}
                    defaultValue={product?.pack_size ?? 1}
                    required
                />
                <InputError message={errors.pack_size} />
            </div>

            <div className="grid gap-2 md:col-span-6">
                <div className="flex items-center space-x-3">
                    <input type="hidden" name="sellable" value="0" />
                    <Checkbox
                        id="sellable"
                        name="sellable"
                        value="1"
                        defaultChecked={product?.sellable ?? true}
                    />
                    <FieldLabel
                        htmlFor="sellable"
                        help={t('catalog.products.form.sellable_help')}
                    >
                        {t('catalog.products.form.sellable')}
                    </FieldLabel>
                </div>
                <InputError message={errors.sellable} />
            </div>
        </>
    );
}
