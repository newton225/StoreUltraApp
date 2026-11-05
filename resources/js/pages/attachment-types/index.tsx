import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/AttachmentTypeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { FieldLabel } from '@/components/field-label';

type AttachmentType = {
    id: number;
    code: string;
    label_fr: string;
    label_en: string;
    applies_to: string;
    required: boolean;
    has_expiry: boolean;
    on_documents: boolean;
    active: boolean;
};

type Flag = 'required' | 'has_expiry' | 'on_documents' | 'active';

const FLAGS: Flag[] = ['required', 'has_expiry', 'on_documents', 'active'];

export default function AttachmentTypesIndex({
    types,
    kinds,
}: {
    types: AttachmentType[];
    kinds: string[];
}) {
    const { t, locale } = useTranslation();
    const [newKind, setNewKind] = useState('company');
    const [editing, setEditing] = useState<AttachmentType | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: t('attachments.types.title'), href: index() }],
    });

    // Printing on our documents only makes sense for the papers of the company itself.
    const flags = (prefix: string, type?: AttachmentType, kind?: string) => (
        <div className="flex flex-wrap items-center gap-4">
            {FLAGS.filter(
                (flag) =>
                    flag !== 'on_documents' ||
                    (type?.applies_to ?? kind) === 'company',
            ).map((flag) => (
                <div key={flag} className="flex items-center space-x-2">
                    <input type="hidden" name={flag} value="0" />
                    <Checkbox
                        id={`${prefix}-${flag}`}
                        name={flag}
                        value="1"
                        defaultChecked={type ? type[flag] : flag === 'active'}
                    />
                    <FieldLabel
                        htmlFor={`${prefix}-${flag}`}
                        help={t(`attachments.types.${flag}_help`)}
                    >
                        {t(`attachments.types.${flag}`)}
                    </FieldLabel>
                </div>
            ))}
        </div>
    );

    return (
        <>
            <Head title={t('attachments.types.head')} />

            <div className="max-w-3xl space-y-8 p-4">
                <Heading
                    title={t('attachments.types.title')}
                    description={t('attachments.types.description')}
                />

                {types.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('attachments.types.empty')}
                    </p>
                )}

                <Form
                    {...store.form()}
                    resetOnSuccess
                    className="space-y-4 rounded-lg border border-border p-4"
                >
                    {({ processing, errors: formErrors }) => {
                        const errors: Record<string, string | undefined> =
                            formErrors;

                        return (
                            <>
                                <h2 className="text-base font-semibold">
                                    {t('attachments.types.new_title')}
                                </h2>
                                <div className="grid items-start gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="new-applies"
                                            help={t(
                                                'attachments.types.applies_to_help',
                                            )}
                                        >
                                            {t('attachments.types.applies_to')}
                                        </FieldLabel>
                                        <SelectField
                                            id="new-applies"
                                            name="applies_to"
                                            required
                                            defaultValue="company"
                                            onChange={setNewKind}
                                            options={kinds.map((kind) => ({
                                                value: kind,
                                                label: t(
                                                    `attachments.kinds.${kind}`,
                                                ),
                                            }))}
                                        />
                                        <InputError
                                            message={errors.applies_to}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-fr">
                                            {t('attachments.types.label_fr')}
                                        </Label>
                                        <Input
                                            id="new-fr"
                                            name="label_fr"
                                            required
                                        />
                                        <InputError message={errors.label_fr} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-en">
                                            {t('attachments.types.label_en')}
                                        </Label>
                                        <Input
                                            id="new-en"
                                            name="label_en"
                                            required
                                        />
                                        <InputError message={errors.label_en} />
                                    </div>
                                </div>
                                {flags('new', undefined, newKind)}
                                <Button variant="success" disabled={processing}>
                                    {t('attachments.types.add')}
                                </Button>
                            </>
                        );
                    }}
                </Form>

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    {t('attachments.types.columns.name')}
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    {t('attachments.types.applies_to')}
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    {t('attachments.types.columns.rules')}
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    {t('attachments.types.columns.status')}
                                </th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {types.map((type) => (
                                <tr
                                    key={type.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2 font-medium">
                                        {locale === 'en'
                                            ? type.label_en
                                            : type.label_fr}
                                    </td>
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        {t(
                                            `attachments.kinds.${type.applies_to}`,
                                        )}
                                    </td>
                                    <td className="px-4 py-2">
                                        <ul className="flex flex-wrap gap-1">
                                            {(
                                                [
                                                    'required',
                                                    'has_expiry',
                                                    'on_documents',
                                                ] as const
                                            )
                                                .filter((flag) => type[flag])
                                                .map((flag) => (
                                                    <li key={flag}>
                                                        <Badge variant="outline">
                                                            {t(
                                                                `attachments.types.${flag}`,
                                                            )}
                                                        </Badge>
                                                    </li>
                                                ))}
                                        </ul>
                                    </td>
                                    <td className="px-4 py-2">
                                        <Badge
                                            variant={
                                                type.active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {t(
                                                type.active
                                                    ? 'attachments.types.active'
                                                    : 'attachments.types.inactive',
                                            )}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setEditing(type)}
                                        >
                                            {t('attachments.types.edit')}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Dialog
                    open={editing !== null}
                    onOpenChange={(value) => !value && setEditing(null)}
                >
                    <DialogContent className="max-w-2xl">
                        {editing && (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {locale === 'en'
                                            ? editing.label_en
                                            : editing.label_fr}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t(
                                            `attachments.kinds.${editing.applies_to}`,
                                        )}
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    {...update.form(editing.id)}
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => setEditing(null)}
                                    className="space-y-4"
                                >
                                    {({ processing, errors: formErrors }) => {
                                        const errors: Record<
                                            string,
                                            string | undefined
                                        > = formErrors;

                                        return (
                                            <>
                                                <div className="grid items-start gap-3 sm:grid-cols-2">
                                                    <input
                                                        type="hidden"
                                                        name="applies_to"
                                                        value={
                                                            editing.applies_to
                                                        }
                                                    />
                                                    <div className="grid gap-1">
                                                        <Label htmlFor="edit-type-fr">
                                                            {t(
                                                                'attachments.types.label_fr',
                                                            )}
                                                        </Label>
                                                        <Input
                                                            id="edit-type-fr"
                                                            name="label_fr"
                                                            defaultValue={
                                                                editing.label_fr
                                                            }
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.label_fr
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label htmlFor="edit-type-en">
                                                            {t(
                                                                'attachments.types.label_en',
                                                            )}
                                                        </Label>
                                                        <Input
                                                            id="edit-type-en"
                                                            name="label_en"
                                                            defaultValue={
                                                                editing.label_en
                                                            }
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.label_en
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                                {flags(
                                                    `type-${editing.id}`,
                                                    editing,
                                                )}
                                                <Button
                                                    variant="success"
                                                    disabled={processing}
                                                    size="sm"
                                                >
                                                    {t(
                                                        'attachments.types.submit',
                                                    )}
                                                </Button>
                                            </>
                                        );
                                    }}
                                </Form>
                            </>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
