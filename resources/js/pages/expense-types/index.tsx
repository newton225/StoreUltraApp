import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/ExpenseTypeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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

type ExpenseType = {
    id: number;
    code: string;
    label_fr: string;
    label_en: string;
    active: boolean;
};

export default function ExpenseTypesIndex({ types }: { types: ExpenseType[] }) {
    const { t, locale } = useTranslation();
    const [editing, setEditing] = useState<ExpenseType | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: t('expenses.types.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('expenses.types.head')} />

            <div className="max-w-3xl space-y-8 p-4">
                <Heading
                    title={t('expenses.types.title')}
                    description={t('expenses.types.description')}
                />

                {types.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('expenses.types.empty')}
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
                                    {t('expenses.types.new_title')}
                                </h2>
                                <div className="grid items-start gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-fr">
                                            {t('expenses.types.label_fr')}
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
                                            {t('expenses.types.label_en')}
                                        </Label>
                                        <Input
                                            id="new-en"
                                            name="label_en"
                                            required
                                        />
                                        <InputError message={errors.label_en} />
                                    </div>
                                </div>
                                <Button variant="success" disabled={processing}>
                                    {t('expenses.types.add')}
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
                                    {t('expenses.types.columns.name')}
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    {t('expenses.types.columns.status')}
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
                                    <td className="px-4 py-2">
                                        <Badge
                                            variant={
                                                type.active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {t(
                                                type.active
                                                    ? 'expenses.types.active'
                                                    : 'expenses.types.inactive',
                                            )}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setEditing(type)}
                                        >
                                            {t('expenses.types.edit')}
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
                    <DialogContent>
                        {editing && (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {locale === 'en'
                                            ? editing.label_en
                                            : editing.label_fr}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t('expenses.types.edit_description')}
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
                                                <div className="grid items-start gap-4 sm:grid-cols-2">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="edit-fr">
                                                            {t(
                                                                'expenses.types.label_fr',
                                                            )}
                                                        </Label>
                                                        <Input
                                                            id="edit-fr"
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
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="edit-en">
                                                            {t(
                                                                'expenses.types.label_en',
                                                            )}
                                                        </Label>
                                                        <Input
                                                            id="edit-en"
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
                                                <div className="flex items-center space-x-2">
                                                    <input
                                                        type="hidden"
                                                        name="active"
                                                        value="0"
                                                    />
                                                    <Checkbox
                                                        id="edit-active"
                                                        name="active"
                                                        value="1"
                                                        defaultChecked={
                                                            editing.active
                                                        }
                                                    />
                                                    <Label htmlFor="edit-active">
                                                        {t(
                                                            'expenses.types.active',
                                                        )}
                                                    </Label>
                                                </div>
                                                <Button
                                                    variant="success"
                                                    disabled={processing}
                                                >
                                                    {t('expenses.types.submit')}
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
