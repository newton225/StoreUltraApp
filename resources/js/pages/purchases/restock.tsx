import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    createRestock,
    index,
    storeRestock,
} from '@/actions/App/Http/Controllers/PurchaseController';
import ConfirmAction from '@/components/confirm-action';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useLeaveGuard } from '@/hooks/use-leave-guard';
import { useTranslation } from '@/hooks/use-translation';

type Product = {
    id: number;
    name: string;
    unit: string;
    pack_size: number;
    price: number | null;
};
type Shop = { id: number; name: string; products: Product[] };

type Props = { shops: Shop[] };

export default function RestockCreate({ shops }: Props) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const [shopId, setShopId] = useState('');
    const [packs, setPacks] = useState<Record<number, string>>({});
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [processing, setProcessing] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: t('purchases.title'), href: index() },
            { title: t('purchases.restock.title'), href: createRestock() },
        ],
    });

    const products =
        shops.find((shop) => String(shop.id) === shopId)?.products ?? [];
    const lines = products
        .map((product) => ({
            product,
            packs: Math.floor(Number(packs[product.id] ?? 0)),
        }))
        .filter((line) => line.packs > 0);
    const total = lines.reduce(
        (sum, line) =>
            sum +
            line.packs * line.product.pack_size * (line.product.price ?? 0),
        0,
    );

    useLeaveGuard(Object.keys(packs).length > 0, t('common.form.leave'));

    const send = (): void => {
        router.post(
            storeRestock.url(),
            {
                shop_id: Number(shopId),
                lines: lines.map((line) => ({
                    product_id: line.product.id,
                    packs: line.packs,
                })),
            },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (received) => setErrors(received),
                onSuccess: () => setPacks({}),
            },
        );
    };

    return (
        <>
            <Head title={t('purchases.restock.title')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title={t('purchases.restock.title')}
                    description={t('purchases.restock.description')}
                />

                <div className="grid max-w-md gap-2">
                    <Label htmlFor="restock-shop">
                        {t('purchases.restock.shop')}
                        <span className="text-destructive" aria-hidden>
                            {' '}
                            *
                        </span>
                    </Label>
                    <SelectField
                        id="restock-shop"
                        searchable
                        name="shop_id"
                        required
                        onChange={(value) => {
                            setShopId(value);
                            setPacks({});
                        }}
                        options={[
                            { value: '', label: t('purchases.restock.choose') },
                            ...shops.map((shop) => ({
                                value: String(shop.id),
                                label: shop.name,
                            })),
                        ]}
                    />
                    <InputError message={errors.shop_id} />
                </div>

                {shopId !== '' &&
                    (products.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('purchases.restock.no_products')}
                        </p>
                    ) : (
                        <div className="space-y-3 fade-in">
                            <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                                {products.map((product, index) => {
                                    const count = Math.floor(
                                        Number(packs[product.id] ?? 0),
                                    );
                                    const units = count * product.pack_size;

                                    return (
                                        <li
                                            key={product.id}
                                            className={`grid items-center gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_8rem_minmax(0,12rem)] ${index % 2 === 1 ? 'bg-muted/30' : ''}`}
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {product.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {t(
                                                        'purchases.restock.pack',
                                                        {
                                                            size: product.pack_size,
                                                            unit: product.unit,
                                                            price:
                                                                product.price ===
                                                                null
                                                                    ? '—'
                                                                    : money(
                                                                          product.price,
                                                                      ),
                                                        },
                                                    )}
                                                </div>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`restock-packs-${product.id}`}
                                                    className="text-xs font-normal text-muted-foreground"
                                                >
                                                    {t(
                                                        'purchases.restock.packs',
                                                    )}
                                                </Label>
                                                <Input
                                                    id={`restock-packs-${product.id}`}
                                                    type="number"
                                                    min={0}
                                                    step={1}
                                                    inputMode="numeric"
                                                    className="tabular-nums"
                                                    value={
                                                        packs[product.id] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        setPacks((current) => ({
                                                            ...current,
                                                            [product.id]:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                />
                                            </div>
                                            <div className="text-right text-muted-foreground tabular-nums">
                                                {units > 0
                                                    ? t(
                                                          'purchases.restock.units',
                                                          {
                                                              units,
                                                              unit: product.unit,
                                                          },
                                                      )
                                                    : ''}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>

                            <InputError message={errors.lines} />

                            <p className="text-sm">
                                {t('purchases.restock.estimate')} :{' '}
                                <strong>{money(Math.round(total))}</strong>
                            </p>

                            <ConfirmAction
                                label={t('purchases.restock.submit')}
                                title={t('purchases.restock.confirm_title')}
                                description={t(
                                    'purchases.restock.confirm_body',
                                )}
                                disabled={lines.length === 0 || processing}
                                onConfirm={send}
                            />
                        </div>
                    ))}
            </div>
        </>
    );
}
