import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    download,
    store,
} from '@/actions/App/Http/Controllers/DocumentController';
import { fne } from '@/actions/App/Http/Controllers/InvoiceController';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import ConfirmAction from '@/components/confirm-action';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

export type DocumentTarget = {
    type: string;
    subject_id: number;
    label: string | null;
    detail?:
        | { kind: string; amount: number; on: string }
        | { kits: number; on: string };
    document: { id: number; number: string; locale: string } | null;
    can_issue: boolean;
};

export type OrderDocumentsData = {
    targets: DocumentTarget[];
    fne_required: boolean;
    invoice: {
        id: number;
        number: string;
        fne_status: string;
        fne_number: string | null;
    } | null;
    can_manage: boolean;
};

export default function OrderDocuments({
    documents,
}: {
    documents: OrderDocumentsData;
}) {
    const { t, locale } = useTranslation();
    const { money, date } = useFormat();
    const [language, setLanguage] = useState(locale);
    const [fneNumber, setFneNumber] = useState('');
    const [recordedFne, setRecordedFne] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const issue = (target: DocumentTarget): void => {
        router.post(
            store.url(),
            {
                type: target.type,
                subject_id: target.subject_id,
                locale: language,
                ...(target.type === 'invoice' && fneNumber
                    ? { fne_number: fneNumber }
                    : {}),
            },
            {
                preserveScroll: true,
                onError: (received) => setErrors(received),
                onSuccess: () => setErrors({}),
            },
        );
    };

    const label = (target: DocumentTarget): string =>
        target.label
            ? t(`documents.ui.panel.${target.type}`, { number: target.label })
            : t(`documents.ui.panel.${target.type}`);

    const detailText = (
        detail: NonNullable<DocumentTarget['detail']>,
    ): string =>
        'kind' in detail
            ? `${t(`payments.kinds.${detail.kind}`)} · ${money(detail.amount)} · ${date(detail.on)}`
            : `${t('documents.ui.panel.kits', { count: detail.kits })} · ${date(detail.on)}`;

    const visible = documents.targets.filter(
        (target) => target.document !== null || target.can_issue,
    );

    if (visible.length === 0) {
        return null;
    }

    return (
        <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-base font-semibold">
                    {t('documents.ui.panel.title')}
                </h2>
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-muted-foreground">
                        {t('documents.ui.panel.language')}
                    </span>
                    <div className="w-28">
                        <SelectField
                            id="document-language"
                            name="document_language"
                            defaultValue={language}
                            onChange={setLanguage}
                            options={[
                                { value: 'fr', label: t('common.language.fr') },
                                { value: 'en', label: t('common.language.en') },
                            ]}
                        />
                    </div>
                </div>
            </div>

            {documents.fne_required &&
                visible.some(
                    (target) => target.type === 'invoice' && target.can_issue,
                ) && (
                    <div className="max-w-xs">
                        <Input
                            value={fneNumber}
                            onChange={(event) =>
                                setFneNumber(event.target.value)
                            }
                            placeholder={t('documents.ui.panel.fne_number')}
                            aria-label={t('documents.ui.panel.fne_number')}
                        />
                    </div>
                )}

            <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                {visible.map((target) => (
                    <li
                        key={`${target.type}-${target.subject_id}`}
                        data-test={`document-${target.type}`}
                        className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                    >
                        <span className="font-medium">
                            {label(target)}
                            {target.detail && (
                                <span className="ml-2 font-normal text-muted-foreground">
                                    {detailText(target.detail)}
                                </span>
                            )}
                        </span>
                        {target.document ? (
                            <span className="flex items-center gap-3">
                                <Badge variant="outline">
                                    {target.document.number}
                                </Badge>
                                <a
                                    href={download.url(target.document.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline underline-offset-4"
                                >
                                    {t('documents.ui.open')}
                                </a>
                            </span>
                        ) : (
                            <ConfirmAction
                                variant="outline"
                                label={t(
                                    `documents.ui.panel.generate.${target.type}`,
                                )}
                                title={t('documents.ui.panel.confirm_title', {
                                    document: label(target),
                                })}
                                description={t(
                                    'documents.ui.panel.confirm_body',
                                )}
                                size="sm"
                                onConfirm={() => issue(target)}
                            />
                        )}
                    </li>
                ))}
            </ul>
            <InputError message={errors.type} />

            {documents.invoice &&
                documents.invoice.fne_status === 'to_issue' &&
                documents.can_manage && (
                    <div className="flex max-w-md flex-wrap items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {t('documents.ui.panel.fne_pending')}
                        </span>
                        <Input
                            value={recordedFne}
                            onChange={(event) =>
                                setRecordedFne(event.target.value)
                            }
                            aria-label={t('documents.ui.panel.fne_number')}
                        />
                        <ConfirmAction
                            size="sm"
                            variant="outline"
                            label={t('documents.ui.panel.fne_record')}
                            title={t('documents.ui.panel.fne_confirm_title')}
                            description={t(
                                'documents.ui.panel.fne_confirm_body',
                            )}
                            onConfirm={() =>
                                router.patch(
                                    fne.url(documents.invoice!.id),
                                    { fne_number: recordedFne },
                                    {
                                        preserveScroll: true,
                                        onError: (received) =>
                                            setErrors(received),
                                    },
                                )
                            }
                        />
                        <InputError message={errors.fne_number} />
                    </div>
                )}
        </section>
    );
}
