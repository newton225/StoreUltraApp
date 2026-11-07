import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { index as attachments } from '@/actions/App/Http/Controllers/AttachmentController';
import {
    destroyLogo,
    logo,
    storeLogo,
    update,
} from '@/actions/App/Http/Controllers/CompanyController';
import ConfirmAction from '@/components/confirm-action';
import { FieldLabel } from '@/components/field-label';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LogoCropper } from '@/components/logo-cropper';
import { SaveBar } from '@/components/save-bar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid, span } from '@/lib/form-layout';
import { edit } from '@/routes/company';

type Props = {
    values: Record<string, string | null>;
    hasLogo: boolean;
    identifiers: string[];
};

/**
 * Who the company is: what is printed, the same way, in the header and the footer of every document.
 * The logo has its own upload; the other details are one form with one save bar.
 */
export default function CompanyEdit({ values, hasLogo, identifiers }: Props) {
    const { t } = useTranslation();
    const [stamp, setStamp] = useState(() => Date.now());
    const [chosen, setChosen] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [cropping, setCropping] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const replaceFile = (file: File | null) => {
        const input = fileInput.current;

        if (input !== null) {
            const transfer = new DataTransfer();

            if (file !== null) {
                transfer.items.add(file);
            }

            input.files = transfer.files;
        }

        setChosen(file);
    };

    useEffect(() => {
        if (chosen === null) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(chosen);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [chosen]);

    setLayoutProps({
        breadcrumbs: [{ title: t('company.head'), href: edit() }],
    });

    return (
        <>
            <Head title={t('company.head')} />

            <div className="max-w-5xl space-y-8 p-4">
                <Heading
                    title={t('company.title')}
                    description={t('company.description')}
                />

                <section className="space-y-3 rounded-lg border border-border p-4">
                    <h2 className="text-base font-semibold">
                        {t('company.sections.logo')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('company.logo.help')}
                    </p>

                    <div className="flex flex-wrap items-center gap-4">
                        {preview !== null && chosen !== null && cropping ? (
                            <LogoCropper
                                src={preview}
                                name={chosen.name}
                                onCancel={() => {
                                    replaceFile(null);
                                    setCropping(false);
                                }}
                                onCropped={(file) => {
                                    replaceFile(file);
                                    setCropping(false);
                                }}
                            />
                        ) : preview !== null ? (
                            <div className="grid gap-1">
                                <img
                                    src={preview}
                                    alt={t('company.logo.preview_alt')}
                                    className="max-h-24 max-w-48 rounded-md border border-dashed border-border bg-white object-contain p-2"
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('company.logo.preview')}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-fit"
                                    onClick={() => setCropping(true)}
                                >
                                    {t('company.logo.crop')}
                                </Button>
                            </div>
                        ) : hasLogo ? (
                            <img
                                src={`${logo.url()}?v=${stamp}`}
                                alt={t('company.logo.alt')}
                                className="max-h-24 max-w-48 rounded-md border border-border bg-white object-contain p-2"
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('company.logo.none')}
                            </p>
                        )}
                    </div>

                    <Form
                        {...storeLogo.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={() => {
                            setStamp(Date.now());
                            setChosen(null);
                            setCropping(false);
                        }}
                        className="flex flex-wrap items-start gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="company-logo">
                                        {t('company.logo.choose')}
                                    </FieldLabel>
                                    <Input
                                        ref={fileInput}
                                        id="company-logo"
                                        name="logo"
                                        type="file"
                                        accept="image/png,image/jpeg"
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0] ?? null;
                                            setChosen(file);
                                            setCropping(file !== null);
                                        }}
                                    />
                                    <InputError message={errors.logo} />
                                </div>
                                <Button
                                    type="submit"
                                    variant="success"
                                    className="mt-7"
                                    disabled={chosen === null || processing}
                                >
                                    {t('company.logo.upload')}
                                </Button>
                                {hasLogo && (
                                    <ConfirmAction
                                        label={t('company.logo.remove')}
                                        title={t('company.logo.remove_title')}
                                        description={t(
                                            'company.logo.remove_body',
                                        )}
                                        variant="ghostDanger"
                                        destructive
                                        className="mt-7"
                                        onConfirm={() =>
                                            router.delete(destroyLogo.url(), {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setStamp(Date.now()),
                                            })
                                        }
                                    />
                                )}
                            </>
                        )}
                    </Form>
                </section>

                <Form
                    setDefaultsOnSuccess
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-8"
                >
                    {({ processing, isDirty, errors }) => {
                        const text = (key: string, width: string) => (
                            <div key={key} className={`grid gap-2 ${width}`}>
                                <FieldLabel
                                    htmlFor={key}
                                    help={t(`company.fields.${key}_help`)}
                                >
                                    {t(`company.fields.${key}`)}
                                </FieldLabel>
                                <Input
                                    id={key}
                                    name={key}
                                    type={
                                        key === 'company_email'
                                            ? 'email'
                                            : key === 'company_website'
                                              ? 'url'
                                              : 'text'
                                    }
                                    maxLength={255}
                                    defaultValue={values[key] ?? ''}
                                />
                                <InputError message={errors[key]} />
                            </div>
                        );

                        return (
                            <>
                                <section className={formGrid}>
                                    <h2 className="col-span-full text-base font-semibold">
                                        {t('company.sections.identity')}
                                    </h2>
                                    {text('company_name', span.twoThirds)}
                                    {text('company_legal_form', span.third)}
                                    {text('company_capital', span.half)}
                                    {text('company_tax_office', span.half)}
                                </section>

                                <section className="space-y-2 rounded-lg border border-border p-4">
                                    <h2 className="text-base font-semibold">
                                        {t('company.identifiers.title')}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {t('company.identifiers.help')}
                                    </p>
                                    {identifiers.length > 0 ? (
                                        <ul className="text-sm">
                                            {identifiers.map((line) => (
                                                <li key={line}>{line}</li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            {t('company.identifiers.none')}
                                        </p>
                                    )}
                                    <Link
                                        href={attachments()}
                                        className="text-sm underline underline-offset-4"
                                    >
                                        {t('company.identifiers.link')}
                                    </Link>
                                </section>

                                <section className={formGrid}>
                                    <h2 className="col-span-full text-base font-semibold">
                                        {t('company.sections.contacts')}
                                    </h2>
                                    {text('company_address', span.twoThirds)}
                                    {text('company_phone', span.third)}
                                    {text('company_email', span.half)}
                                    {text('company_website', span.half)}
                                    {text('company_signatory', span.half)}
                                    {text('company_signatory_title', span.half)}
                                </section>

                                <section className={formGrid}>
                                    <h2 className="col-span-full text-base font-semibold">
                                        {t('company.sections.documents')}
                                    </h2>
                                    {text('document_project_title', span.half)}
                                    {text('document_subtitle', span.half)}
                                    <div className={`grid gap-2 ${span.full}`}>
                                        <FieldLabel
                                            htmlFor="company_footer_note"
                                            help={t(
                                                'company.fields.company_footer_note_help',
                                            )}
                                        >
                                            {t(
                                                'company.fields.company_footer_note',
                                            )}
                                        </FieldLabel>
                                        <Textarea
                                            id="company_footer_note"
                                            name="company_footer_note"
                                            rows={3}
                                            maxLength={500}
                                            defaultValue={
                                                values.company_footer_note ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.company_footer_note}
                                        />
                                    </div>
                                </section>

                                <SaveBar
                                    dirty={isDirty}
                                    processing={processing}
                                >
                                    {t('parameters.save')}
                                </SaveBar>
                            </>
                        );
                    }}
                </Form>
            </div>
        </>
    );
}
