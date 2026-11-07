import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/ShopController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { useCanDo } from '@/hooks/use-can-do';

type Shop = {
    id: number;
    name: string;
    contact: string | null;
    phone: string | null;
    active: boolean;
    products_count: number;
};

export default function ShopsIndex({ shops }: { shops: Shop[] }) {
    const canEdit = useCanDo('catalog.shops');
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('catalog.shops.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('catalog.shops.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('catalog.shops.title')}
                        description={t('catalog.shops.description')}
                    />
                    {canEdit && (
                        <Button variant="info" asChild>
                            <Link href={create()}>
                                {t('catalog.shops.new')}
                            </Link>
                        </Button>
                    )}
                </div>

                {shops.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('catalog.shops.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('catalog.shops.columns.name')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('catalog.shops.columns.contact')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('catalog.shops.columns.phone')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('catalog.shops.columns.products')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('catalog.shops.columns.status')}
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {shops.map((shop) => (
                                    <tr
                                        key={shop.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2 font-medium">
                                            {shop.name}
                                        </td>
                                        <td className="px-4 py-2">
                                            {shop.contact ?? '—'}
                                        </td>
                                        <td className="px-4 py-2">
                                            {shop.phone && (
                                                <a
                                                    href={`tel:${shop.phone}`}
                                                    className="underline-offset-4 hover:underline"
                                                >
                                                    {shop.phone}
                                                </a>
                                            )}
                                        </td>
                                        <td className="px-4 py-2">
                                            {shop.products_count}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Badge
                                                variant={
                                                    shop.active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {shop.active
                                                    ? t('catalog.status.active')
                                                    : t(
                                                          'catalog.status.inactive',
                                                      )}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {canEdit && (
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link href={edit(shop.id)}>
                                                        {t(
                                                            'catalog.shops.edit',
                                                        )}
                                                    </Link>
                                                </Button>
                                            )}
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
