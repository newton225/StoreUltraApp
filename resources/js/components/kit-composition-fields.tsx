import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Hint } from '@/components/hint';
import { useTranslation } from '@/hooks/use-translation';
import { FieldLabel } from '@/components/field-label';

export type ProductOption = { id: number; name: string; unit: string };

export type CompositionLine = {
    product_id: number | null;
    quantity_per_kit: number;
    sale_price_per_kit: number | null;
};

type Row = {
    key: number;
    product_id: string;
    quantity: string;
    sale: string;
};

type Props = {
    products: ProductOption[];
    initialLines: CompositionLine[];
    initialMode: string;
    initialFixedPrice: number | null;
    today: string;
    errors: Record<string, string | undefined>;
};

let nextKey = 0;

const toRow = (line: CompositionLine): Row => ({
    key: nextKey++,
    product_id: line.product_id === null ? '' : String(line.product_id),
    quantity: String(line.quantity_per_kit),
    sale:
        line.sale_price_per_kit === null ? '' : String(line.sale_price_per_kit),
});

export default function KitCompositionFields({
    products,
    initialLines,
    initialMode,
    initialFixedPrice,
    today,
    errors,
}: Props) {
    const { t } = useTranslation();
    const [mode, setMode] = useState(initialMode);
    const [rows, setRows] = useState<Row[]>(() =>
        initialLines.length > 0
            ? initialLines.map(toRow)
            : [
                  toRow({
                      product_id: null,
                      quantity_per_kit: 1,
                      sale_price_per_kit: null,
                  }),
              ],
    );

    const update = (key: number, patch: Partial<Row>): void =>
        setRows((current) =>
            current.map((row) =>
                row.key === key ? { ...row, ...patch } : row,
            ),
        );

    return (
        <div className="space-y-6">
            <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor="sale_price_mode"
                        help={t('kits.composition.sale_price_mode_help')}
                    >
                        {t('kits.composition.sale_price_mode')}
                    </FieldLabel>
                    <select
                        id="sale_price_mode"
                        name="sale_price_mode"
                        value={mode}
                        onChange={(event) => setMode(event.target.value)}
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                    >
                        <option value="lines">
                            {t('kits.composition.mode_lines')}
                        </option>
                        <option value="fixed">
                            {t('kits.composition.mode_fixed')}
                        </option>
                    </select>
                    <InputError message={errors.sale_price_mode} />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor="valid_from"
                        help={t('kits.composition.valid_from_help')}
                    >
                        {t('kits.composition.valid_from')}
                    </FieldLabel>
                    <Input
                        id="valid_from"
                        name="valid_from"
                        type="date"
                        defaultValue={today}
                        required
                    />
                    <InputError message={errors.valid_from} />
                </div>
            </div>

            {mode === 'fixed' && (
                <div className="grid gap-2">
                    <Label htmlFor="fixed_sale_price">
                        {t('kits.composition.fixed_sale_price')}
                    </Label>
                    <Input
                        id="fixed_sale_price"
                        name="fixed_sale_price"
                        type="number"
                        min={0}
                        step={1}
                        defaultValue={initialFixedPrice ?? ''}
                    />
                    <InputError message={errors.fixed_sale_price} />
                </div>
            )}

            <div className="space-y-3">
                <h3 className="text-sm font-medium">
                    {t('kits.composition.title')}
                </h3>
                <InputError message={errors.items} />

                {rows.map((row, index) => (
                    <div
                        key={row.key}
                        className="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-[1fr_7rem_9rem_auto] sm:items-start"
                    >
                        <div className="grid gap-1">
                            <Label
                                htmlFor={`product-${row.key}`}
                                className="sm:sr-only"
                            >
                                {t('kits.composition.product')}
                            </Label>
                            <SelectField
                                id={`product-${row.key}`}
                                name={`items[${index}][product_id]`}
                                required
                                defaultValue={row.product_id}
                                options={[
                                    {
                                        value: '',
                                        label: t(
                                            'kits.composition.choose_product',
                                        ),
                                    },
                                    ...products.map((product) => ({
                                        value: String(product.id),
                                        label: `${product.name} (${product.unit})`,
                                    })),
                                ]}
                            />
                            <InputError
                                message={errors[`items.${index}.product_id`]}
                            />
                        </div>

                        <div className="grid gap-1">
                            <Label
                                htmlFor={`quantity-${row.key}`}
                                className="sm:sr-only"
                            >
                                {t('kits.composition.quantity')}
                            </Label>
                            <Input
                                id={`quantity-${row.key}`}
                                name={`items[${index}][quantity_per_kit]`}
                                type="number"
                                min={1}
                                step={1}
                                value={row.quantity}
                                onChange={(event) =>
                                    update(row.key, {
                                        quantity: event.target.value,
                                    })
                                }
                                placeholder={t('kits.composition.quantity')}
                            />
                            <InputError
                                message={
                                    errors[`items.${index}.quantity_per_kit`]
                                }
                            />
                        </div>

                        <div className="grid gap-1">
                            <Label
                                htmlFor={`sale-${row.key}`}
                                className="sm:sr-only"
                            >
                                {t('kits.composition.line_sale_price')}
                            </Label>
                            <Input
                                id={`sale-${row.key}`}
                                name={`items[${index}][sale_price_per_kit]`}
                                type="number"
                                min={0}
                                step={1}
                                value={row.sale}
                                onChange={(event) =>
                                    update(row.key, {
                                        sale: event.target.value,
                                    })
                                }
                                placeholder={t(
                                    'kits.composition.line_sale_price',
                                )}
                            />
                            <InputError
                                message={
                                    errors[`items.${index}.sale_price_per_kit`]
                                }
                            />
                        </div>

                        <Hint label={t('kits.composition.remove_line')}>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    setRows((current) =>
                                        current.filter(
                                            (r) => r.key !== row.key,
                                        ),
                                    )
                                }
                                disabled={rows.length === 1}
                            >
                                <Trash2 className="h-4 w-4" />
                                <span className="sr-only">
                                    {t('kits.composition.remove_line')}
                                </span>
                            </Button>
                        </Hint>
                    </div>
                ))}

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        setRows((current) => [
                            ...current,
                            toRow({
                                product_id: null,
                                quantity_per_kit: 1,
                                sale_price_per_kit: null,
                            }),
                        ])
                    }
                >
                    <Plus className="h-4 w-4" />
                    {t('kits.composition.add_line')}
                </Button>
            </div>
        </div>
    );
}
