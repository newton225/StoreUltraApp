import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/ProductComponentController';
import { Hint } from '@/components/hint';
import InputError from '@/components/input-error';
import { SaveBar } from '@/components/save-bar';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export type ComponentLine = {
    component_product_id: number;
    name: string;
    unit: string;
    quantity: number;
};

export type ComponentOption = { id: number; name: string; unit: string };

type Row = { key: number; product_id: string; quantity: string };

type Props = {
    productId: number;
    components: ComponentLine[];
    options: ComponentOption[];
    /** The product is already a component of another one: it cannot be made of others. */
    isComponent: boolean;
    /** Bought ready-made from a shop: the product itself, not its components, is on the purchase order. */
    boughtWhole: boolean;
};

let nextKey = 0;

export default function ProductComponents({
    productId,
    components,
    options,
    isComponent,
    boughtWhole,
}: Props) {
    const { t } = useTranslation();
    const [rows, setRows] = useState<Row[]>(() =>
        components.map((component) => ({
            key: nextKey++,
            product_id: String(component.component_product_id),
            quantity: String(component.quantity),
        })),
    );

    if (isComponent) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('catalog.components.is_component')}
            </p>
        );
    }

    const patch = (key: number, change: Partial<Row>): void =>
        setRows((current) =>
            current.map((row) =>
                row.key === key ? { ...row, ...change } : row,
            ),
        );

    return (
        <Form
            {...update.form(productId)}
            options={{ preserveScroll: true }}
            className="space-y-4"
        >
            {({ processing, isDirty, errors: formErrors }) => {
                const errors: Record<string, string | undefined> = formErrors;

                return (
                    <>
                        {rows.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('catalog.components.empty')}
                            </p>
                        )}

                        {rows.map((row, index) => (
                            <div
                                key={row.key}
                                className="grid items-start gap-2 sm:grid-cols-[1fr_9rem_auto]"
                            >
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`component-${row.key}`}
                                        className="sm:sr-only"
                                    >
                                        {t('catalog.components.product')}
                                    </Label>
                                    <SelectField
                                        id={`component-${row.key}`}
                                        searchable
                                        name={`components[${index}][component_product_id]`}
                                        required
                                        defaultValue={row.product_id}
                                        options={[
                                            {
                                                value: '',
                                                label: t(
                                                    'catalog.components.choose',
                                                ),
                                            },
                                            ...options.map((option) => ({
                                                value: String(option.id),
                                                label: `${option.name} (${option.unit})`,
                                            })),
                                        ]}
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `components.${index}.component_product_id`
                                            ]
                                        }
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`component-qty-${row.key}`}
                                        className="sm:sr-only"
                                    >
                                        {t('catalog.components.quantity')}
                                    </Label>
                                    <Input
                                        id={`component-qty-${row.key}`}
                                        name={`components[${index}][quantity]`}
                                        type="number"
                                        min={0}
                                        step="any"
                                        required
                                        value={row.quantity}
                                        onChange={(event) =>
                                            patch(row.key, {
                                                quantity: event.target.value,
                                            })
                                        }
                                        placeholder={t(
                                            'catalog.components.quantity',
                                        )}
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `components.${index}.quantity`
                                            ]
                                        }
                                    />
                                </div>

                                <Hint label={t('catalog.components.remove')}>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            setRows((current) =>
                                                current.filter(
                                                    (item) =>
                                                        item.key !== row.key,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span className="sr-only">
                                            {t('catalog.components.remove')}
                                        </span>
                                    </Button>
                                </Hint>
                            </div>
                        ))}

                        <InputError message={errors.components} />

                        <div className="flex items-start space-x-3">
                            <input
                                type="hidden"
                                name="bought_whole"
                                value="0"
                            />
                            <Checkbox
                                id="bought_whole"
                                name="bought_whole"
                                value="1"
                                defaultChecked={boughtWhole}
                                className="mt-0.5"
                            />
                            <div className="grid gap-0.5">
                                <Label htmlFor="bought_whole">
                                    {t('catalog.components.bought_whole')}
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    {t('catalog.components.bought_whole_help')}
                                </p>
                            </div>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setRows((current) => [
                                    ...current,
                                    {
                                        key: nextKey++,
                                        product_id: '',
                                        quantity: '1',
                                    },
                                ])
                            }
                        >
                            <Plus className="h-4 w-4" />
                            {t('catalog.components.add')}
                        </Button>

                        <SaveBar
                            dirty={isDirty || rows.length !== components.length}
                            processing={processing}
                        >
                            {t('catalog.components.save')}
                        </SaveBar>
                    </>
                );
            }}
        </Form>
    );
}
