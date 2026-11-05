import InputError from '@/components/input-error';
import { FieldLabel } from '@/components/field-label';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    today: string;
    /** A first price starts today: the date is not asked, only a later price needs one. */
    hideDate?: boolean;
    errors: Record<string, string | undefined>;
};

export default function PriceFields({
    today,
    errors,
    hideDate = false,
}: Props) {
    const { t } = useTranslation();
    const span = hideDate ? 'md:col-span-3' : 'md:col-span-2';

    return (
        <>
            <div className={`grid gap-2 ${span}`}>
                <Label htmlFor="purchase_price">
                    {t('catalog.products.form.purchase_price')}
                </Label>
                <Input
                    id="purchase_price"
                    name="purchase_price"
                    type="number"
                    min={0}
                    step={1}
                    required
                />
                <InputError message={errors.purchase_price} />
            </div>

            <div className={`grid gap-2 ${span}`}>
                <Label htmlFor="sale_price">
                    {t('catalog.products.form.sale_price')}
                </Label>
                <Input
                    id="sale_price"
                    name="sale_price"
                    type="number"
                    min={0}
                    step={1}
                />
                <p className="text-xs text-muted-foreground">
                    {t('catalog.products.form.sale_price_help')}
                </p>
                <InputError message={errors.sale_price} />
            </div>

            {hideDate ? (
                <input type="hidden" name="effective_from" value={today} />
            ) : (
                <div className={`grid gap-2 ${span}`}>
                    <FieldLabel
                        htmlFor="effective_from"
                        help={t('catalog.products.form.effective_from_help')}
                    >
                        {t('catalog.products.form.effective_from')}
                    </FieldLabel>
                    <Input
                        id="effective_from"
                        name="effective_from"
                        type="date"
                        defaultValue={today}
                        required
                    />
                    <InputError message={errors.effective_from} />
                </div>
            )}
        </>
    );
}
