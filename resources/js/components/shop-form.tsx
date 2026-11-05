import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { SaveBar } from '@/components/save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';

export type EditableShop = {
    id: number;
    name: string;
    contact: string | null;
    phone: string | null;
    address: string | null;
    payment_terms: string | null;
    active: boolean;
};

type Props = {
    formProps: { action: string; method: 'post' };
    shop?: EditableShop;
};

const SHOP_SPANS = {
    name: 'md:col-span-3',
    contact: 'md:col-span-3',
    phone: 'md:col-span-2',
    payment_terms: 'md:col-span-4',
    address: 'col-span-full',
} as const;

const TEXT_FIELDS = [
    'name',
    'contact',
    'phone',
    'payment_terms',
    'address',
] as const;

export default function ShopForm({ formProps, shop }: Props) {
    const { t } = useTranslation();

    return (
        <Form setDefaultsOnSuccess {...formProps} className={formGrid}>
            {({ processing, isDirty, errors: formErrors }) => {
                const errors: Record<string, string | undefined> = formErrors;

                return (
                    <>
                        {TEXT_FIELDS.map((field) => (
                            <div
                                key={field}
                                className={`grid gap-2 ${SHOP_SPANS[field]}`}
                            >
                                <Label htmlFor={field}>
                                    {t(`catalog.shops.form.${field}`)}
                                </Label>
                                <Input
                                    id={field}
                                    name={field}
                                    defaultValue={shop?.[field] ?? ''}
                                    required={field === 'name'}
                                />
                                <InputError message={errors[field]} />
                            </div>
                        ))}

                        {shop && (
                            <div className="col-span-full flex items-center space-x-3">
                                <input type="hidden" name="active" value="0" />
                                <Checkbox
                                    id="active"
                                    name="active"
                                    value="1"
                                    defaultChecked={shop.active}
                                />
                                <Label htmlFor="active">
                                    {t('catalog.shops.form.active')}
                                </Label>
                            </div>
                        )}

                        <SaveBar
                            dirty={isDirty}
                            processing={processing}
                            isNew={!shop}
                        >
                            {t('catalog.shops.form.submit')}
                        </SaveBar>
                    </>
                );
            }}
        </Form>
    );
}
