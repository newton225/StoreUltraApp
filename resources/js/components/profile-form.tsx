import { Form } from '@inertiajs/react';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import { SaveBar } from '@/components/save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';

export type EditableProfile = {
    id: number;
    label_fr: string;
    label_en: string;
    restricted_to_zones: boolean;
    is_system: boolean;
    permissions: string[];
};

type Props = {
    formProps: { action: string; method: 'post' };
    /** Every permission of the closed catalogue. */
    permissions: string[];
    profile?: EditableProfile;
    /** What a new profile starts from: the rights of an existing profile. */
    template?: { restricted_to_zones: boolean; permissions: string[] } | null;
    /** A system profile is shown, not edited. */
    readOnly?: boolean;
};

/**
 * A profile: its names, whether it is tied to the assigned sub-prefectures, and the rights it grants,
 * each explained. The rights come from the closed catalogue sent by the server.
 */
export default function ProfileForm({
    formProps,
    permissions,
    profile,
    template,
    readOnly = false,
}: Props) {
    const { t } = useTranslation();
    const granted = new Set(
        profile?.permissions ?? template?.permissions ?? [],
    );
    const restricted =
        profile?.restricted_to_zones ?? template?.restricted_to_zones ?? false;

    return (
        <Form setDefaultsOnSuccess {...formProps} className={formGrid}>
            {({ processing, isDirty, errors: formErrors }) => {
                const errors: Record<string, string | undefined> = formErrors;

                return (
                    <fieldset
                        disabled={readOnly}
                        className="col-span-full grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-6"
                    >
                        <div className="grid gap-2 md:col-span-3">
                            <FieldLabel htmlFor="label_fr">
                                {t('profiles.form.label_fr')}
                            </FieldLabel>
                            <Input
                                id="label_fr"
                                name="label_fr"
                                defaultValue={profile?.label_fr ?? ''}
                                required
                            />
                            <InputError message={errors.label_fr} />
                        </div>

                        <div className="grid gap-2 md:col-span-3">
                            <FieldLabel htmlFor="label_en">
                                {t('profiles.form.label_en')}
                            </FieldLabel>
                            <Input
                                id="label_en"
                                name="label_en"
                                defaultValue={profile?.label_en ?? ''}
                                required
                            />
                            <InputError message={errors.label_en} />
                        </div>

                        <div className="col-span-full grid gap-2">
                            <input
                                type="hidden"
                                name="restricted_to_zones"
                                value="0"
                            />
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="restricted_to_zones"
                                    name="restricted_to_zones"
                                    value="1"
                                    defaultChecked={restricted}
                                />
                                <FieldLabel
                                    htmlFor="restricted_to_zones"
                                    help={t('profiles.form.restricted_help')}
                                >
                                    {t('profiles.form.restricted')}
                                </FieldLabel>
                            </div>
                            <InputError message={errors.restricted_to_zones} />
                        </div>

                        <div className="col-span-full grid gap-3">
                            <div className="space-y-1">
                                <p className="text-sm font-medium">
                                    {t('profiles.form.permissions')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('profiles.form.permissions_help')}
                                </p>
                            </div>
                            <ul className="grid gap-3 rounded-lg border border-border p-4 md:grid-cols-2">
                                {permissions.map((permission) => (
                                    <li
                                        key={permission}
                                        className="flex items-center gap-3"
                                    >
                                        <Checkbox
                                            id={`permission-${permission}`}
                                            name="permissions[]"
                                            value={permission}
                                            defaultChecked={granted.has(
                                                permission,
                                            )}
                                        />
                                        <FieldLabel
                                            htmlFor={`permission-${permission}`}
                                            help={t(
                                                `profiles.permissions.${permission}_help`,
                                            )}
                                        >
                                            {t(
                                                `profiles.permissions.${permission}`,
                                            )}
                                        </FieldLabel>
                                    </li>
                                ))}
                            </ul>
                            <InputError message={errors.permissions} />
                        </div>

                        {!readOnly && (
                            <SaveBar
                                dirty={isDirty}
                                processing={processing}
                                isNew={!profile}
                            >
                                {t('profiles.form.submit')}
                            </SaveBar>
                        )}
                    </fieldset>
                );
            }}
        </Form>
    );
}
