import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy,
    file,
    index,
    store,
} from '@/actions/App/Http/Controllers/AttachmentController';
import ExportMenu from '@/components/export-menu';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { PageLinks } from '@/components/pagination';
import Pagination from '@/components/pagination';
import SelectField from '@/components/select-field';
import ConfirmAction from '@/components/confirm-action';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { FieldLabel } from '@/components/field-label';
import TabLinks from '@/components/tab-links';

type Row = {
    id: number;
    type: string;
    owner: string;
    number: string;
    issued_on: string | null;
    expires_on: string | null;
    notes: string | null;
    has_file: boolean;
    file_type: 'image' | 'pdf';
};

type Expiring = {
    id: number;
    type_id: number;
    owner_id: number;
    type: string;
    kind: string;
    owner: string;
    number: string;
    expires_on: string;
    days: number;
};

type Props = {
    kind: string;
    kinds: string[];
    attachments: PageLinks & { data: Row[]; total: number };
    search: string;
    owners: { id: number; name: string }[];
    types: {
        id: number;
        name: string;
        required: boolean;
        has_expiry: boolean;
    }[];
    preset: { type: number | null; owner: number | null };
    attachmentAlerts: {
        missing: {
            type_id: number;
            owner_id: number;
            type: string;
            kind: string;
            owner: string;
        }[];
        expiring: Expiring[];
        expired: Expiring[];
    };
};

export default function AttachmentsIndex({
    kind,
    kinds,
    attachments,
    search: currentSearch,
    owners,
    types,
    attachmentAlerts: alerts,
    preset,
}: Props) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const [search, setSearch] = useState(currentSearch);

    setLayoutProps({
        breadcrumbs: [{ title: t('attachments.title'), href: index() }],
    });

    const mine = {
        missing: alerts.missing.filter((row) => row.kind === kind),
        expired: alerts.expired.filter((row) => row.kind === kind),
        expiring: alerts.expiring.filter((row) => row.kind === kind),
    };
    const hasAlerts =
        mine.missing.length + mine.expired.length + mine.expiring.length > 0;

    const [open, setOpen] = useState(preset.type !== null);
    const [viewing, setViewing] = useState<Row | null>(null);

    const fill = (kindOfRow: string, type: number, owner: number) =>
        index({ query: { kind: kindOfRow, type, owner } });

    const when = (row: Expiring): string =>
        row.days >= 0
            ? t('attachments.alerts.in_days', { days: row.days })
            : t('attachments.alerts.since_days', { days: -row.days });

    return (
        <>
            <Head title={t('attachments.head')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('attachments.title')}
                        description={t('attachments.description')}
                    />
                    <ExportMenu
                        view="attachments"
                        query={{ kind, search: currentSearch }}
                    />
                </div>

                <TabLinks
                    label={t('attachments.title')}
                    current={kind}
                    tabs={kinds.map((item) => ({
                        key: item,
                        label: t(`attachments.kinds.${item}`),
                        href: index({ query: { kind: item } }).url,
                        count: [
                            ...alerts.missing,
                            ...alerts.expired,
                            ...alerts.expiring,
                        ].filter((row) => row.kind === item).length,
                    }))}
                />

                <div key={kind} className="space-y-6 fade-in">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {t('attachments.count', {
                                count: attachments.total,
                            })}
                        </p>
                        {types.length > 0 && (
                            <Button
                                variant="info"
                                onClick={() => setOpen(true)}
                            >
                                {t('attachments.form.open')}
                            </Button>
                        )}
                    </div>

                    {hasAlerts && (
                        <section className="space-y-2">
                            <h2 className="text-base font-semibold">
                                {t('attachments.alerts.title')}
                            </h2>
                            <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                                {mine.missing.map((row) => (
                                    <li
                                        key={`missing-${row.kind}-${row.type}-${row.owner}`}
                                        className="flex items-center justify-between gap-3 px-4 py-2"
                                    >
                                        <span>
                                            <strong>
                                                {t(
                                                    'attachments.alerts.missing',
                                                )}
                                            </strong>{' '}
                                            · {row.type} · {row.owner}
                                        </span>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={fill(
                                                    row.kind,
                                                    row.type_id,
                                                    row.owner_id,
                                                )}
                                            >
                                                {t('attachments.alerts.add')}
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                                {mine.expired.map((row) => (
                                    <li
                                        key={`expired-${row.id}`}
                                        className="flex items-center justify-between gap-3 px-4 py-2"
                                    >
                                        <span>
                                            <strong>
                                                {t(
                                                    'attachments.alerts.expired',
                                                )}
                                            </strong>{' '}
                                            · {row.type} · {row.owner} ·{' '}
                                            {row.number} ·{' '}
                                            {date(row.expires_on)} ({when(row)})
                                        </span>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={fill(
                                                    row.kind,
                                                    row.type_id,
                                                    row.owner_id,
                                                )}
                                            >
                                                {t('attachments.alerts.renew')}
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                                {mine.expiring.map((row) => (
                                    <li
                                        key={`soon-${row.id}`}
                                        className="flex items-center justify-between gap-3 px-4 py-2"
                                    >
                                        <span>
                                            <strong>
                                                {t(
                                                    'attachments.alerts.expiring',
                                                )}
                                            </strong>{' '}
                                            · {row.type} · {row.owner} ·{' '}
                                            {row.number} ·{' '}
                                            {date(row.expires_on)} ({when(row)})
                                        </span>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={fill(
                                                    row.kind,
                                                    row.type_id,
                                                    row.owner_id,
                                                )}
                                            >
                                                {t('attachments.alerts.renew')}
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <form
                        className="flex max-w-md gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                index.url(),
                                {
                                    kind,
                                    ...(search.trim() !== ''
                                        ? { search: search.trim() }
                                        : {}),
                                },
                                { preserveState: true, replace: true },
                            );
                        }}
                    >
                        <Input
                            className="flex-1"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('attachments.search')}
                            aria-label={t('attachments.search')}
                        />
                        <Button type="submit" variant="secondary">
                            {t('attachments.search_apply')}
                        </Button>
                    </form>

                    {attachments.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('attachments.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        {(
                                            [
                                                'type',
                                                'owner',
                                                'number',
                                                'issued_on',
                                                'expires_on',
                                            ] as const
                                        ).map((column) => (
                                            <th
                                                key={column}
                                                className="px-3 py-2 font-medium"
                                            >
                                                {t(
                                                    `attachments.columns.${column}`,
                                                )}
                                            </th>
                                        ))}
                                        <th className="px-3 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {attachments.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-3 py-2">
                                                {row.type}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.owner}
                                            </td>
                                            <td className="px-3 py-2 font-medium">
                                                {row.number}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.issued_on
                                                    ? date(row.issued_on)
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.expires_on
                                                    ? date(row.expires_on)
                                                    : '—'}
                                            </td>
                                            <td className="flex items-center justify-end gap-3 px-3 py-2">
                                                {row.has_file && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setViewing(row)
                                                        }
                                                    >
                                                        {t(
                                                            'attachments.open_file',
                                                        )}
                                                    </Button>
                                                )}
                                                <ConfirmAction
                                                    variant="ghost"
                                                    size="sm"
                                                    destructive
                                                    label={t(
                                                        'attachments.delete',
                                                    )}
                                                    title={t(
                                                        'attachments.delete_title',
                                                    )}
                                                    description={t(
                                                        'attachments.delete_confirm',
                                                    )}
                                                    onConfirm={() =>
                                                        router.delete(
                                                            destroy.url(row.id),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination page={attachments} />

                    {types.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t('attachments.form.no_types')}
                        </p>
                    )}
                </div>

                <Dialog
                    open={viewing !== null}
                    onOpenChange={(value) => !value && setViewing(null)}
                >
                    <DialogContent className="max-w-3xl">
                        {viewing && (
                            <>
                                <DialogHeader>
                                    <DialogTitle>{viewing.type}</DialogTitle>
                                    <DialogDescription>
                                        {viewing.owner} · {viewing.number}
                                        {viewing.expires_on &&
                                            ` · ${date(viewing.expires_on)}`}
                                    </DialogDescription>
                                </DialogHeader>
                                {viewing.file_type === 'image' ? (
                                    <img
                                        src={file.url(viewing.id)}
                                        alt={t('attachments.preview_alt')}
                                        className="max-h-[60vh] w-full rounded-md border border-border object-contain"
                                    />
                                ) : (
                                    <p className="rounded-md border border-border p-6 text-center text-sm text-muted-foreground">
                                        {t('attachments.pdf_notice')}
                                    </p>
                                )}
                                <div className="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => setViewing(null)}
                                    >
                                        {t('attachments.close')}
                                    </Button>
                                    <Button asChild variant="info">
                                        <a
                                            href={file.url(viewing.id, {
                                                query: { download: 1 },
                                            })}
                                        >
                                            {t('attachments.download')}
                                        </a>
                                    </Button>
                                </div>
                            </>
                        )}
                    </DialogContent>
                </Dialog>

                <Dialog open={open && types.length > 0} onOpenChange={setOpen}>
                    <DialogContent className="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>
                                {t(`attachments.form.title_${kind}`)}
                            </DialogTitle>
                            <DialogDescription>
                                {t('attachments.form.description')}
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            key={`${kind}-${preset.type}-${preset.owner}`}
                            {...store.form()}
                            resetOnSuccess
                            onSuccess={() => setOpen(false)}
                            className="space-y-4"
                        >
                            {({ processing, errors: formErrors }) => {
                                const errors: Record<
                                    string,
                                    string | undefined
                                > = formErrors;

                                return (
                                    <>
                                        <div className="grid items-start gap-4 sm:grid-cols-3">
                                            <div
                                                className={`grid gap-2 ${kind === 'company' ? 'sm:col-span-2' : ''}`}
                                            >
                                                <Label htmlFor="attachment_type_id">
                                                    {t('attachments.form.type')}
                                                </Label>
                                                <SelectField
                                                    id="attachment_type_id"
                                                    name="attachment_type_id"
                                                    required
                                                    defaultValue={String(
                                                        preset.type ?? '',
                                                    )}
                                                    options={[
                                                        {
                                                            value: '',
                                                            label: t(
                                                                'attachments.form.choose_type',
                                                            ),
                                                        },
                                                        ...types.map(
                                                            (type) => ({
                                                                value: String(
                                                                    type.id,
                                                                ),
                                                                label: type.name,
                                                            }),
                                                        ),
                                                    ]}
                                                />
                                                <InputError
                                                    message={
                                                        errors.attachment_type_id
                                                    }
                                                />
                                            </div>

                                            {kind !== 'company' && (
                                                <div className="grid gap-2">
                                                    <FieldLabel
                                                        htmlFor="owner_id"
                                                        help={t(
                                                            'attachments.form.owner_help',
                                                        )}
                                                    >
                                                        {t(
                                                            'attachments.form.owner',
                                                        )}
                                                    </FieldLabel>
                                                    <SelectField
                                                        id="owner_id"
                                                        searchable
                                                        name="owner_id"
                                                        prompt
                                                        defaultValue={String(
                                                            preset.owner ?? '',
                                                        )}
                                                        options={[
                                                            {
                                                                value: '',
                                                                label: t(
                                                                    'attachments.form.choose_owner',
                                                                ),
                                                            },
                                                            ...owners.map(
                                                                (owner) => ({
                                                                    value: String(
                                                                        owner.id,
                                                                    ),
                                                                    label: owner.name,
                                                                }),
                                                            ),
                                                        ]}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.owner_id
                                                        }
                                                    />
                                                </div>
                                            )}

                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="number"
                                                    help={t(
                                                        'attachments.form.number_help',
                                                    )}
                                                >
                                                    {t(
                                                        'attachments.form.number',
                                                    )}
                                                </FieldLabel>
                                                <Input
                                                    id="number"
                                                    name="number"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.number}
                                                />
                                            </div>
                                        </div>

                                        <div className="grid items-start gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="issued_on">
                                                    {t(
                                                        'attachments.form.issued_on',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="issued_on"
                                                    name="issued_on"
                                                    type="date"
                                                />
                                                <InputError
                                                    message={errors.issued_on}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="expires_on">
                                                    {t(
                                                        'attachments.form.expires_on',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="expires_on"
                                                    name="expires_on"
                                                    type="date"
                                                />
                                                <InputError
                                                    message={errors.expires_on}
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="notes">
                                                {t('attachments.form.notes')}
                                            </Label>
                                            <Textarea
                                                rows={3}
                                                id="notes"
                                                name="notes"
                                            />
                                            <InputError
                                                message={errors.notes}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="file">
                                                {t('attachments.form.file')}
                                            </Label>
                                            <Input
                                                id="file"
                                                name="file"
                                                type="file"
                                                accept=".pdf,.jpg,.jpeg,.png"
                                            />
                                            <InputError message={errors.file} />
                                        </div>

                                        <input
                                            type="hidden"
                                            name="confirm_duplicate"
                                            value={errors.duplicate ? '1' : '0'}
                                        />
                                        {errors.duplicate && (
                                            <p
                                                role="alert"
                                                className="rounded-md border border-amber-500/50 bg-amber-500/10 p-3 text-sm fade-in"
                                            >
                                                {errors.duplicate}
                                            </p>
                                        )}
                                        <Button
                                            variant="success"
                                            disabled={processing}
                                        >
                                            {t(
                                                errors.duplicate
                                                    ? 'attachments.form.submit_anyway'
                                                    : 'attachments.form.submit',
                                            )}
                                        </Button>
                                    </>
                                );
                            }}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
