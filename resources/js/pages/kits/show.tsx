import { Form, Head, setLayoutProps } from '@inertiajs/react';
import {
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/KitController';
import { store as storeVersion } from '@/actions/App/Http/Controllers/KitVersionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { ProductOption } from '@/components/kit-composition-fields';
import KitCompositionFields from '@/components/kit-composition-fields';
import type { Summary } from '@/components/kit-summary';
import KitSummary from '@/components/kit-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type Item = {
    product_id: number;
    product_name: string;
    unit: string;
    quantity_per_kit: number;
    sale_price_per_kit: number | null;
    unit_purchase_price: number | null;
};

type Version = {
    id: number;
    version: number;
    valid_from: string;
    sale_price_mode: string;
    fixed_sale_price: number | null;
    items: Item[];
    summary: Summary;
};

type Props = {
    kit: { id: number; name_fr: string; name_en: string; active: boolean };
    versions: Version[];
    currentVersionId: number | null;
    products: ProductOption[];
    kitsPerClient: number;
    clientCap: number;
    seesCosts: boolean;
    today: string;
};

export default function ShowKit({
    kit,
    versions,
    currentVersionId,
    products,
    kitsPerClient,
    clientCap,
    seesCosts,
    today,
}: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();

    setLayoutProps({
        breadcrumbs: [
            { title: t('kits.title'), href: index() },
            { title: kit.name_fr, href: show(kit.id) },
        ],
    });

    const current = versions.find((version) => version.id === currentVersionId);
    const base = current ?? versions[0];

    const productNames: Record<number, string> = Object.fromEntries(
        versions.flatMap((version) =>
            version.items.map((item) => [item.product_id, item.product_name]),
        ),
    );

    return (
        <>
            <Head title={kit.name_fr} />

            <div className="max-w-4xl space-y-10 p-4">
                <section className="max-w-4xl space-y-6">
                    <Heading title={kit.name_fr} description={kit.name_en} />

                    <Form
                        {...update.form(kit.id)}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        {({ processing, errors: formErrors }) => {
                            const errors: Record<string, string | undefined> =
                                formErrors;

                            return (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name_fr">
                                            {t('kits.form.name_fr')}
                                        </Label>
                                        <Input
                                            id="name_fr"
                                            name="name_fr"
                                            defaultValue={kit.name_fr}
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
                                            defaultValue={kit.name_en}
                                            required
                                        />
                                        <InputError message={errors.name_en} />
                                    </div>
                                    <div className="flex items-center space-x-3 md:col-span-2">
                                        <input
                                            type="hidden"
                                            name="active"
                                            value="0"
                                        />
                                        <Checkbox
                                            id="active"
                                            name="active"
                                            value="1"
                                            defaultChecked={kit.active}
                                        />
                                        <Label htmlFor="active">
                                            {t('kits.form.active')}
                                        </Label>
                                    </div>
                                    <Button
                                        variant="success"
                                        className="justify-self-start"
                                        disabled={processing}
                                    >
                                        {t('kits.form.save')}
                                    </Button>
                                </>
                            );
                        }}
                    </Form>
                </section>

                {current && (
                    <section className="space-y-4">
                        <Heading
                            variant="small"
                            title={t('kits.summary.title')}
                        />
                        <KitSummary
                            summary={current.summary}
                            kitsPerClient={kitsPerClient}
                            defaultClients={clientCap}
                            productNames={productNames}
                            seesCosts={seesCosts}
                        />
                    </section>
                )}

                <section className="space-y-6">
                    <Heading
                        variant="small"
                        title={t('kits.show.versions_title')}
                        description={t('kits.show.versions_description')}
                    />

                    {versions.map((version) => (
                        <div
                            key={version.id}
                            className="space-y-3 rounded-lg border border-border p-4"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <h3 className="font-medium">
                                    {t('kits.show.version', {
                                        number: version.version,
                                    })}
                                </h3>
                                <span className="text-sm text-muted-foreground">
                                    {t('kits.show.from', {
                                        date: date(version.valid_from),
                                    })}
                                </span>
                                {version.id === currentVersionId && (
                                    <Badge variant="success">
                                        {t('kits.show.current')}
                                    </Badge>
                                )}
                                {version.valid_from.slice(0, 10) > today && (
                                    <Badge variant="info">
                                        {t('kits.show.upcoming')}
                                    </Badge>
                                )}
                            </div>

                            <div className="overflow-x-auto rounded-lg border border-border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                {t('kits.composition.product')}
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                {t('kits.composition.quantity')}
                                            </th>
                                            {seesCosts && (
                                                <th className="px-4 py-2 text-right font-medium">
                                                    {t(
                                                        'kits.show.unit_purchase',
                                                    )}
                                                </th>
                                            )}
                                            <th className="px-4 py-2 text-right font-medium">
                                                {t('kits.show.line_sale')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {version.items.map((item) => (
                                            <tr
                                                key={item.product_id}
                                                className="border-t border-border"
                                            >
                                                <td className="px-4 py-2">
                                                    {item.product_name}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    {item.quantity_per_kit}{' '}
                                                    {item.unit}
                                                </td>
                                                {seesCosts && (
                                                    <td className="px-4 py-2 text-right">
                                                        {item.unit_purchase_price !==
                                                        null
                                                            ? money(
                                                                  item.unit_purchase_price,
                                                              )
                                                            : t(
                                                                  'kits.show.missing_price',
                                                              )}
                                                    </td>
                                                )}
                                                <td className="px-4 py-2 text-right">
                                                    {item.sale_price_per_kit !==
                                                    null
                                                        ? money(
                                                              item.sale_price_per_kit,
                                                          )
                                                        : ''}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <p className="text-sm text-muted-foreground">
                                {seesCosts && (
                                    <>
                                        {t('kits.summary.purchase')}:{' '}
                                        {version.summary.purchase !== null
                                            ? money(version.summary.purchase)
                                            : '—'}
                                        {' · '}
                                    </>
                                )}
                                {t('kits.summary.sale')}:{' '}
                                {version.summary.sale !== null
                                    ? money(version.summary.sale)
                                    : '—'}
                                {seesCosts && (
                                    <>
                                        {' · '}
                                        {t('kits.summary.margin')}:{' '}
                                        {version.summary.margin !== null
                                            ? money(version.summary.margin)
                                            : '—'}
                                    </>
                                )}
                            </p>
                        </div>
                    ))}
                </section>

                <section className="space-y-6">
                    <Heading
                        variant="small"
                        title={t('kits.show.new_version_title')}
                        description={t('kits.show.new_version_description')}
                    />

                    <Form
                        {...storeVersion.form(kit.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors: formErrors }) => (
                            <>
                                <KitCompositionFields
                                    products={products}
                                    initialLines={
                                        base
                                            ? base.items.map((item) => ({
                                                  product_id: item.product_id,
                                                  quantity_per_kit:
                                                      item.quantity_per_kit,
                                                  sale_price_per_kit:
                                                      item.sale_price_per_kit,
                                              }))
                                            : []
                                    }
                                    initialMode={
                                        base?.sale_price_mode ?? 'lines'
                                    }
                                    initialFixedPrice={
                                        base?.fixed_sale_price ?? null
                                    }
                                    today={today}
                                    errors={formErrors}
                                />
                                <Button variant="success" disabled={processing}>
                                    {t('kits.show.new_version_submit')}
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}
