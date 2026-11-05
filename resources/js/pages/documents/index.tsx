import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    cancel,
    download,
    index,
    store,
} from '@/actions/App/Http/Controllers/DocumentController';
import Heading from '@/components/heading';
import ExportMenu from '@/components/export-menu';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Doc = {
    id: number;
    type: string;
    number: string;
    locale: string;
    about: string;
    issued_at: string;
    cancelled: boolean;
    cancel_reason: string | null;
};

type Props = {
    documents: {
        data: Doc[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { type: string | null; search: string; state: string | null };
    types: string[];
    period: { start: string; end: string };
};

export default function DocumentsIndex({
    documents,
    filters,
    types,
    period,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [cancelling, setCancelling] = useState<number | null>(null);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    setLayoutProps({
        breadcrumbs: [{ title: t('documents.ui.title'), href: index() }],
    });

    const dateTime = new Intl.DateTimeFormat(locale, { dateStyle: 'short' });

    const apply = (patch: Record<string, string> = {}): void => {
        const next: Record<string, string> = {
            search,
            type: filters.type ?? '',
            state: filters.state ?? '',
            ...patch,
        };

        router.get(
            index.url(),
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, replace: true },
        );
    };

    const cancelDocument = (doc: Doc, replace: boolean): void => {
        router.post(
            cancel.url(doc.id),
            { reason, replace, locale: doc.locale },
            {
                preserveScroll: true,
                onError: (received) => setErrors(received),
                onSuccess: () => {
                    setErrors({});
                    setCancelling(null);
                    setReason('');
                },
            },
        );
    };

    return (
        <>
            <Head title={t('documents.ui.head')} />

            <div className="space-y-8 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('documents.ui.title')}
                        description={t('documents.ui.description')}
                    />
                    <ExportMenu view="documents" query={filters} />
                </div>

                <section className="space-y-3 rounded-lg border border-border p-4">
                    <h2 className="text-base font-semibold">
                        {t('documents.ui.statement.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('documents.ui.statement.description')}
                    </p>
                    <Form
                        {...store.form()}
                        options={{ preserveScroll: true }}
                        className="flex flex-wrap items-end gap-3"
                    >
                        {({ processing, errors: formErrors }) => {
                            const e: Record<string, string | undefined> =
                                formErrors;

                            return (
                                <>
                                    <input
                                        type="hidden"
                                        name="type"
                                        value="statement"
                                    />
                                    <input
                                        type="hidden"
                                        name="locale"
                                        value={locale}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="period_start">
                                            {t('documents.ui.statement.start')}
                                        </Label>
                                        <Input
                                            id="period_start"
                                            name="period_start"
                                            type="date"
                                            defaultValue={period.start}
                                            required
                                        />
                                        <InputError message={e.period_start} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="period_end">
                                            {t('documents.ui.statement.end')}
                                        </Label>
                                        <Input
                                            id="period_end"
                                            name="period_end"
                                            type="date"
                                            defaultValue={period.end}
                                            required
                                        />
                                        <InputError message={e.period_end} />
                                    </div>
                                    <Button
                                        variant="success"
                                        disabled={processing}
                                    >
                                        {t('documents.ui.statement.submit')}
                                    </Button>
                                    <InputError message={e.type} />
                                </>
                            );
                        }}
                    </Form>
                </section>

                <section className="space-y-4">
                    <form
                        className="grid gap-3 sm:grid-cols-[minmax(0,26rem)_14rem_10rem]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply();
                        }}
                    >
                        <div className="flex gap-2">
                            <Input
                                className="flex-1"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t('documents.ui.filters.search')}
                                aria-label={t('documents.ui.filters.search')}
                            />
                            <Button type="submit" variant="secondary">
                                {t('documents.ui.filters.apply')}
                            </Button>
                        </div>
                        <SelectField
                            id="filter-type"
                            name="type"
                            defaultValue={filters.type ?? ''}
                            onChange={(value) => apply({ type: value })}
                            options={[
                                {
                                    value: '',
                                    label: t('documents.ui.filters.all_types'),
                                },
                                ...types.map((type) => ({
                                    value: type,
                                    label: t(`documents.ui.types.${type}`),
                                })),
                            ]}
                        />
                        <SelectField
                            id="filter-state"
                            name="state"
                            defaultValue={filters.state ?? ''}
                            onChange={(value) => apply({ state: value })}
                            options={[
                                {
                                    value: '',
                                    label: t('documents.ui.filters.all_states'),
                                },
                                {
                                    value: 'valid',
                                    label: t('documents.ui.filters.valid'),
                                },
                                {
                                    value: 'cancelled',
                                    label: t('documents.ui.filters.cancelled'),
                                },
                            ]}
                        />
                    </form>

                    {documents.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('documents.ui.empty')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                            {documents.data.map((doc) => (
                                <li
                                    key={doc.id}
                                    className="space-y-2 px-4 py-3"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="space-y-0.5">
                                            <div className="font-medium">
                                                {doc.number}{' '}
                                                <Badge variant="outline">
                                                    {t(
                                                        `documents.ui.types.${doc.type}`,
                                                    )}
                                                </Badge>{' '}
                                                {doc.cancelled && (
                                                    <Badge variant="secondary">
                                                        {t(
                                                            'documents.ui.state_cancelled',
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {doc.about} ·{' '}
                                                {doc.locale.toUpperCase()} ·{' '}
                                                {dateTime.format(
                                                    new Date(doc.issued_at),
                                                )}
                                                {doc.cancel_reason &&
                                                    ` · ${doc.cancel_reason}`}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <a
                                                href={download.url(doc.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="underline underline-offset-4"
                                            >
                                                {t('documents.ui.open')}
                                            </a>
                                            {!doc.cancelled &&
                                                cancelling !== doc.id && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setCancelling(
                                                                doc.id,
                                                            )
                                                        }
                                                    >
                                                        {t(
                                                            'documents.ui.cancel',
                                                        )}
                                                    </Button>
                                                )}
                                        </div>
                                    </div>

                                    {cancelling === doc.id && (
                                        <div className="max-w-md space-y-2">
                                            <Input
                                                value={reason}
                                                onChange={(event) =>
                                                    setReason(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'documents.ui.cancel_reason',
                                                )}
                                                aria-label={t(
                                                    'documents.ui.cancel_reason',
                                                )}
                                            />
                                            <InputError
                                                message={
                                                    errors.reason ?? errors.type
                                                }
                                            />
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        cancelDocument(
                                                            doc,
                                                            true,
                                                        )
                                                    }
                                                >
                                                    {t(
                                                        'documents.ui.cancel_replace',
                                                    )}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        cancelDocument(
                                                            doc,
                                                            false,
                                                        )
                                                    }
                                                >
                                                    {t(
                                                        'documents.ui.cancel_only',
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <Pagination page={documents} />
                </section>
            </div>
        </>
    );
}
