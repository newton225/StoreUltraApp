import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/KitController';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import { HelpHint } from '@/components/hint';
import type { Summary } from '@/components/kit-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { useCanDo } from '@/hooks/use-can-do';

type ListedKit = {
    id: number;
    name: string;
    active: boolean;
    current_version: {
        id: number;
        version: number;
        valid_from: string;
        summary: Summary;
    } | null;
};

export default function KitsIndex({
    kits,
    seesCosts,
}: {
    kits: ListedKit[];
    seesCosts: boolean;
}) {
    const canCreate = useCanDo('catalog.kits');
    const { t } = useTranslation();
    const { money } = useFormat();

    setLayoutProps({
        breadcrumbs: [{ title: t('kits.title'), href: index() }],
    });

    const amount = (value: number | null): string =>
        value === null ? '—' : money(value);

    return (
        <>
            <Head title={t('kits.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('kits.title')}
                        description={t('kits.description')}
                    />
                    <div className="flex shrink-0 items-center gap-2">
                        <ExportMenu view="kits" />
                        {canCreate && (
                            <Button variant="info" asChild>
                                <Link href={create()}>{t('kits.new')}</Link>
                            </Button>
                        )}
                    </div>
                </div>

                {kits.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('kits.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('kits.columns.name')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        <span className="flex items-center gap-1.5">
                                            {t('kits.columns.version')}
                                            <HelpHint
                                                label={t(
                                                    'kits.columns.version_help',
                                                )}
                                            />
                                        </span>
                                    </th>
                                    {seesCosts && (
                                        <th className="px-4 py-2 text-right font-medium">
                                            <span className="inline-flex items-center justify-end gap-1.5">
                                                {t('kits.columns.purchase')}
                                                <HelpHint
                                                    label={t(
                                                        'kits.columns.purchase_help',
                                                    )}
                                                />
                                            </span>
                                        </th>
                                    )}
                                    <th className="px-4 py-2 text-right font-medium">
                                        {t('kits.columns.sale')}
                                    </th>
                                    {seesCosts && (
                                        <th className="px-4 py-2 text-right font-medium">
                                            <span className="inline-flex items-center justify-end gap-1.5">
                                                {t('kits.columns.margin')}
                                                <HelpHint
                                                    label={t(
                                                        'kits.columns.margin_help',
                                                    )}
                                                />
                                            </span>
                                        </th>
                                    )}
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {kits.map((kit) => (
                                    <tr
                                        key={kit.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2 font-medium">
                                            {kit.name}
                                            {!kit.active && (
                                                <Badge
                                                    variant="neutral"
                                                    className="ml-2"
                                                >
                                                    {t(
                                                        'catalog.status.inactive',
                                                    )}
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-2">
                                            {kit.current_version
                                                ? kit.current_version.version
                                                : t('kits.no_version')}
                                        </td>
                                        {seesCosts && (
                                            <td className="px-4 py-2 text-right">
                                                {amount(
                                                    kit.current_version?.summary
                                                        .purchase ?? null,
                                                )}
                                            </td>
                                        )}
                                        <td className="px-4 py-2 text-right">
                                            {amount(
                                                kit.current_version?.summary
                                                    .sale ?? null,
                                            )}
                                        </td>
                                        {seesCosts && (
                                            <td className="px-4 py-2 text-right">
                                                {amount(
                                                    kit.current_version?.summary
                                                        .margin ?? null,
                                                )}
                                            </td>
                                        )}
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link href={show(kit.id)}>
                                                    {t('kits.open')}
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
