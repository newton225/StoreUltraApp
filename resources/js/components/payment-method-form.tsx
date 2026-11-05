import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { SaveBar } from '@/components/save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';

export type EditableMethod = {
    id: number;
    code: string;
    label_fr: string;
    label_en: string;
    requires_reference: boolean;
    active: boolean;
};

type Props = {
    formProps: { action: string; method: 'post' };
    method?: EditableMethod;
};

export default function PaymentMethodForm({ formProps, method }: Props) {
    const { t } = useTranslation();

    return (
        <Form setDefaultsOnSuccess {...formProps} className={formGrid}>
            {({ processing, isDirty, errors: formErrors }) => {
                const errors: Record<string, string | undefined> = formErrors;

                return (
                    <>
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="label_fr">
                                {t('payments.methods.form.label_fr')}
                            </Label>
                            <Input
                                id="label_fr"
                                name="label_fr"
                                defaultValue={method?.label_fr}
                                required
                            />
                            <InputError message={errors.label_fr} />
                        </div>

                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="label_en">
                                {t('payments.methods.form.label_en')}
                            </Label>
                            <Input
                                id="label_en"
                                name="label_en"
                                defaultValue={method?.label_en}
                                required
                            />
                            <InputError message={errors.label_en} />
                        </div>

                        <div className="col-span-full flex items-center space-x-3">
                            <input
                                type="hidden"
                                name="requires_reference"
                                value="0"
                            />
                            <Checkbox
                                id="requires_reference"
                                name="requires_reference"
                                value="1"
                                defaultChecked={method?.requires_reference}
                            />
                            <Label htmlFor="requires_reference">
                                {t('payments.methods.form.requires_reference')}
                            </Label>
                        </div>

                        {method && (
                            <div className="col-span-full flex items-center space-x-3">
                                <input type="hidden" name="active" value="0" />
                                <Checkbox
                                    id="active"
                                    name="active"
                                    value="1"
                                    defaultChecked={method.active}
                                />
                                <Label htmlFor="active">
                                    {t('payments.methods.form.active')}
                                </Label>
                            </div>
                        )}

                        <SaveBar
                            dirty={isDirty}
                            processing={processing}
                            isNew={!method}
                        >
                            {t('payments.methods.form.submit')}
                        </SaveBar>
                    </>
                );
            }}
        </Form>
    );
}
