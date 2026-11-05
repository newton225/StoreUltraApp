import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/PaymentMethodController';
import Heading from '@/components/heading';
import type { EditableMethod } from '@/components/payment-method-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

export default function PaymentMethodsIndex({
    methods,
}: {
    methods: EditableMethod[];
}) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('payments.methods.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('payments.methods.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('payments.methods.title')}
                        description={t('payments.methods.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>{t('payments.methods.new')}</Link>
                    </Button>
                </div>

                {methods.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('payments.methods.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        {t('payments.methods.columns.label')}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t(
                                            'payments.methods.columns.reference',
                                        )}
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        {t('payments.methods.columns.status')}
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {methods.map((method) => (
                                    <tr
                                        key={method.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2 font-medium">
                                            {locale === 'en'
                                                ? method.label_en
                                                : method.label_fr}
                                        </td>
                                        <td className="px-4 py-2">
                                            {method.requires_reference
                                                ? t('payments.methods.yes')
                                                : t('payments.methods.no')}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Badge
                                                variant={
                                                    method.active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {method.active
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
                                                <Link href={edit(method.id)}>
                                                    {t('payments.methods.edit')}
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
