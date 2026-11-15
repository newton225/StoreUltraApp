import { Form, Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/KitController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type {
    CompositionLine,
    ProductOption,
} from '@/components/kit-composition-fields';
import KitCompositionFields from '@/components/kit-composition-fields';
import { SaveBar } from '@/components/save-bar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Copy = {
    source: string;
    name_fr: string;
    name_en: string;
    mode: string;
    fixed_price: number | null;
    lines: CompositionLine[];
    skipped: number;
};

type Props = { products: ProductOption[]; today: string; copy: Copy | null };

export default function CreateKit({ products, today, copy }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('kits.title'), href: index() },
            { title: t('kits.create.title'), href: create() },
        ],
    });

    return (
        <>
            <Head title={t('kits.create.head')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title={t('kits.create.title')}
                    description={
                        copy
                            ? t('kits.copy.description', {
                                  name: copy.source,
                              })
                            : t('kits.create.description')
                    }
                />

                {copy && copy.skipped > 0 && (
                    <p
                        role="status"
                        className="rounded-md border border-warning/40 bg-warning/10 px-3 py-2 text-sm"
                    >
                        {t('kits.copy.skipped', { count: copy.skipped })}
                    </p>
                )}

                <Form
                    setDefaultsOnSuccess
                    {...store.form()}
                    className="space-y-6"
                >
                    {({ processing, isDirty, errors: formErrors }) => {
                        const errors: Record<string, string | undefined> =
                            formErrors;

                        return (
                            <>
                                <div className="grid items-start gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name_fr">
                                            {t('kits.form.name_fr')}
                                        </Label>
                                        <Input
                                            id="name_fr"
                                            name="name_fr"
                                            defaultValue={copy?.name_fr}
                                            required
                                        />
                                        <InputError message={errors.name_fr} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name_en">
                                            {t('kits.form.name_en')}
                                        </Label>
                                        <Input
                                            id="name_en"
                                            name="name_en"
                                            defaultValue={copy?.name_en}
                                            required
                                        />
                                        <InputError message={errors.name_en} />
                                    </div>
                                </div>

                                <KitCompositionFields
                                    products={products}
                                    initialLines={copy?.lines ?? []}
                                    initialMode={copy?.mode ?? 'lines'}
                                    initialFixedPrice={
                                        copy?.fixed_price ?? null
                                    }
                                    today={today}
                                    errors={errors}
                                />

                                <SaveBar
                                    dirty={isDirty}
                                    processing={processing}
                                    isNew={true}
                                >
                                    {t('kits.create.submit')}
                                </SaveBar>
                            </>
                        );
                    }}
                </Form>
            </div>
        </>
    );
}
