import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { SaveBar } from '@/components/save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';

export type EditableZone = {
    id: number;
    name: string;
    code: string | null;
    active: boolean;
};

type Props = {
    formProps: { action: string; method: 'post' };
    zone?: EditableZone;
};

export default function ZoneForm({ formProps, zone }: Props) {
    const { t } = useTranslation();

    return (
        <Form setDefaultsOnSuccess {...formProps} className={formGrid}>
            {({ processing, isDirty, errors: formErrors }) => {
                const errors: Record<string, string | undefined> = formErrors;

                return (
                    <>
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="name">
                                {t('catalog.zones.form.name')}
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={zone?.name}
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="code">
                                {t('catalog.zones.form.code')}
                            </Label>
                            <Input
                                id="code"
                                name="code"
                                defaultValue={zone?.code ?? ''}
                            />
                            <InputError message={errors.code} />
                        </div>

                        {zone && (
                            <div className="col-span-full flex items-center space-x-3">
                                <input type="hidden" name="active" value="0" />
                                <Checkbox
                                    id="active"
                                    name="active"
                                    value="1"
                                    defaultChecked={zone.active}
                                />
                                <Label htmlFor="active">
                                    {t('catalog.zones.form.active')}
                                </Label>
                            </div>
                        )}

                        <SaveBar
                            dirty={isDirty}
                            processing={processing}
                            isNew={!zone}
                        >
                            {t('catalog.zones.form.submit')}
                        </SaveBar>
                    </>
                );
            }}
        </Form>
    );
}
