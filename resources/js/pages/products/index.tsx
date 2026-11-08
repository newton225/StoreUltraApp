import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    archive,
    create,
    edit,
    index,
    restore,
} from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import type { PageLinks } from '@/components/pagination';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import SortHeader, { sortQuery } from '@/components/sort-header';
import type { SortState } from '@/components/sort-header';
import { useTranslation } from '@/hooks/use-translation';
import { useVisitBusy } from '@/hooks/use-visit-busy';
import { useCanDo } from '@/hooks/use-can-do';

type Product = {
    id: number;
    name: string;
    unit: string;
    archived_at: string | null;
    shop: { id: number; name: string } | null;
    current_price: { purchase_price: number; sale_price: number | null } | null;
};

type Props = {
    products: PageLinks & { data: Product[] };
    showArchived: boolean;
    search: string;
    sort: SortState;
};

export default function ProductsIndex({
    products,
    showArchived,
    search: currentSearch,
    sort,
}: Props) {
    const canCreate = useCanDo('catalog.products');
    const canArchive = useCanDo('catalog.archive');
    const [search, setSearch] = useState(currentSearch);
    const { t } = useTranslation();
    const busy = useVisitBusy();
    const { money } = useFormat();

    setLayoutProps({
        breadcrumbs: [{ title: t('catalog.products.title'), href: index() }],
    });

    const apply = (archived: boolean): void => {
        router.get(
            index.url(),
            {
                ...(archived ? { archived: 1 } : {}),
                ...(search.trim() !== '' ? { search: search.trim() } : {}),
                ...sortQuery(sort),
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={t('catalog.products.head')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={t('catalog.products.title')}
                        description={t('catalog.products.description')}
                    />
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <ExportMenu
                            view="products"
                            query={{
                                archived: showArchived ? 1 : '',
                                search: currentSearch,
                            }}
                        />
                        {canCreate && (
                            <Button variant="info" asChild>
                                <Link href={create()}>
                                    {t('catalog.products.new')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <form
                    className="flex flex-wrap items-center gap-x-6 gap-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply(showArchived);
                    }}
                >
                    <div className="flex w-full max-w-md gap-2">
                        <Input
                            className="flex-1"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('catalog.products.search')}
                            aria-label={t('catalog.products.search')}
                        />
                        <Button type="submit" variant="secondary">
                            {t('catalog.products.search_apply')}
                        </Button>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={showArchived}
                            onChange={() => apply(!showArchived)}
                        />
                        {t('catalog.products.show_archived')}
                    </label>
                </form>

                {products.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('catalog.products.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <SortHeader column="name" state={sort}>
                                        {t('catalog.products.columns.name')}
                                    </SortHeader>
                                    <SortHeader column="shop" state={sort}>
                                        {t('catalog.products.columns.shop')}
                                    </SortHeader>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t(
                                            'catalog.products.columns.purchase_price',
                                        )}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t(
                                            'catalog.products.columns.sale_price',
                                        )}
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('catalog.products.columns.margin')}
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {products.data.map((product) => {
                                    const price = product.current_price;
                                    const margin =
                                        price?.sale_price != null
                                            ? price.sale_price -
                                              price.purchase_price
                                            : null;

                                    return (
                                        <tr
                                            key={product.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2 font-medium">
                                                {product.name}{' '}
                                                <span className="font-normal text-muted-foreground">
                                                    ({product.unit})
                                                </span>
                                                {product.archived_at && (
                                                    <Badge
                                                        variant="neutral"
                                                        className="ml-2"
                                                    >
                                                        {t(
                                                            'catalog.status.archived',
                                                        )}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-2">
                                                {product.shop?.name}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {price
                                                    ? money(
                                                          price.purchase_price,
                                                      )
                                                    : t(
                                                          'catalog.products.no_price',
                                                      )}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {price?.sale_price != null
                                                    ? money(price.sale_price)
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {margin !== null
                                                    ? money(margin)
                                                    : ''}
                                            </td>
                                            <td className="space-x-1 px-4 py-2 text-right whitespace-nowrap">
                                                {canCreate && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={edit(
                                                                product.id,
                                                            )}
                                                        >
                                                            {t(
                                                                'catalog.products.edit',
                                                            )}
                                                        </Link>
                                                    </Button>
                                                )}
                                                {canArchive && (
                                                    <Button
                                                        disabled={busy}
                                                        variant="outline"
                                                        size="sm"
                                                        className={
                                                            product.archived_at
                                                                ? 'border-success/40 text-success-text hover:bg-success/10 hover:text-success-text'
                                                                : 'border-orange/40 text-orange-text hover:bg-orange/10 hover:text-orange-text'
                                                        }
                                                        onClick={() =>
                                                            router.patch(
                                                                (product.archived_at
                                                                    ? restore
                                                                    : archive
                                                                ).url(
                                                                    product.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {product.archived_at
                                                            ? t(
                                                                  'catalog.products.restore',
                                                              )
                                                            : t(
                                                                  'catalog.products.archive',
                                                              )}
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={products} />
            </div>
        </>
    );
}
