import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { createRestock } from '@/actions/App/Http/Controllers/PurchaseController';
import { index, show } from '@/actions/App/Http/Controllers/StockController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCanDo } from '@/hooks/use-can-do';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

type Item = {
    product_id: number;
    name: string;
    unit: string;
    pack_size: number;
    on_hand: number;
    average_cost: number;
    value: number;
};

type Props = {
    items: Item[];
    totalValue: number;
    filters: { search: string };
};

export default function StockIndex({ items, totalValue, filters }: Props) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const canRestock = useCanDo('purchases.generate');
    const [search, setSearch] = useState(filters.search);

    setLayoutProps({
        breadcrumbs: [{ title: t('stock.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('stock.head')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={t('stock.title')}
                        description={t('stock.description')}
                    />
                    {canRestock && (
                        <Button asChild variant="infoOutline">
                            <Link href={createRestock()}>
                                {t('purchases.restock.open')}
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <form
                        className="w-full max-w-xs"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(index.url(), search ? { search } : {}, {
                                preserveState: true,
                                replace: true,
                            });
                        }}
                    >
                        <label htmlFor="stock-search" className="sr-only">
                            {t('stock.search')}
                        </label>
                        <Input
                            id="stock-search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('stock.search')}
                        />
                    </form>
                    <p className="text-sm">
                        {t('stock.total_value')} :{' '}
                        <strong>{money(totalValue)}</strong>
                    </p>
                </div>

                {items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('stock.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('stock.columns.product')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('stock.columns.on_hand')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('stock.columns.average_cost')}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('stock.columns.value')}
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr
                                        key={item.product_id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2">
                                            <span className="font-medium">
                                                {item.name}
                                            </span>
                                            {item.pack_size > 1 && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {t('stock.pack', {
                                                        size: item.pack_size,
                                                    })}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right whitespace-nowrap">
                                            {item.on_hand} {item.unit}
                                        </td>
                                        <td className="px-4 py-2 text-right whitespace-nowrap">
                                            {money(item.average_cost)}
                                        </td>
                                        <td className="px-4 py-2 text-right whitespace-nowrap">
                                            {money(item.value)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={show(item.product_id)}
                                                >
                                                    {t('stock.open')}
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
