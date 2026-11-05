import { Form, Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/KitController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { ProductOption } from '@/components/kit-composition-fields';
import KitCompositionFields from '@/components/kit-composition-fields';
import { SaveBar } from '@/components/save-bar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Props = { products: ProductOption[]; today: string };

export default function CreateKit({ products, today }: Props) {
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
                    description={t('kits.create.description')}
                />

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
                                            required
                                        />
                                        <InputError message={errors.name_en} />
                                    </div>
                                </div>

                                <KitCompositionFields
                                    products={products}
                                    initialLines={[]}
                                    initialMode="lines"
                                    initialFixedPrice={null}
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
