import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/ZoneController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Zone = { id: number; name: string; code: string | null; active: boolean };

export default function ZonesIndex({ zones }: { zones: Zone[] }) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('catalog.zones.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('catalog.zones.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('catalog.zones.title')}
                        description={t('catalog.zones.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>{t('catalog.zones.new')}</Link>
                    </Button>
                </div>

                {zones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('catalog.zones.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('catalog.zones.columns.name')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('catalog.zones.columns.code')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('catalog.zones.columns.status')}
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {zones.map((zone) => (
                                    <tr
                                        key={zone.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2 font-medium">
                                            {zone.name}
                                        </td>
                                        <td className="px-4 py-2">
                                            {zone.code}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Badge
                                                variant={
                                                    zone.active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {zone.active
                                                    ? t('catalog.status.active')
                                                    : t(
                                                          'catalog.status.inactive',
                                                      )}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link href={edit(zone.id)}>
                                                    {t('catalog.zones.edit')}
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
