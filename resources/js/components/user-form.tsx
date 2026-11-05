import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import SelectField from '@/components/select-field';
import { SaveBar } from '@/components/save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';
import { FieldLabel } from '@/components/field-label';

export type EditableUser = {
    id: number;
    name: string;
    email: string;
    profile_id: number;
    is_active: boolean;
    zone_ids?: number[];
};

export type ZoneOption = { id: number; name: string; active: boolean };

type Props = {
    formProps: { action: string; method: 'post' };
    profiles: { id: number; name: string; restricted_to_zones: boolean }[];
    zones: ZoneOption[];
    passwordRules: string;
    user?: EditableUser;
    isSelf?: boolean;
    submitLabel: string;
};

export default function UserForm({
    formProps,
    profiles,
    zones,
    passwordRules,
    user,
    isSelf = false,
    submitLabel,
}: Props) {
    const { t } = useTranslation();
    const isEdit = user !== undefined;
    const [profileId, setProfileId] = useState(
        String(
            user?.profile_id ??
                profiles.find((profile) => !profile.restricted_to_zones)?.id ??
                '',
        ),
    );
    const restricted =
        profiles.find((profile) => String(profile.id) === profileId)
            ?.restricted_to_zones ?? false;

    return (
        <Form setDefaultsOnSuccess {...formProps} className={formGrid}>
            {({ processing, isDirty, errors: formErrors }) => {
                const errors: Record<string, string | undefined> = formErrors;

                return (
                    <>
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="name">{t('users.form.name')}</Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={user?.name}
                                required
                                autoComplete="off"
                                placeholder={t('users.form.name_placeholder')}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="email">
                                {t('users.form.email')}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                defaultValue={user?.email}
                                required
                                autoComplete="off"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <FieldLabel
                                htmlFor="profile_id"
                                help={t('users.form.role_help')}
                            >
                                {t('users.form.profile')}
                            </FieldLabel>
                            <SelectField
                                id="profile_id"
                                name="profile_id"
                                required
                                defaultValue={profileId}
                                onChange={setProfileId}
                                options={profiles.map((profile) => ({
                                    value: String(profile.id),
                                    label: profile.name,
                                }))}
                            />
                            <InputError
                                message={errors.profile_id ?? errors.role}
                            />
                        </div>

                        {restricted && (
                            <fieldset className="order-last col-span-full grid gap-2">
                                <legend className="text-sm font-medium">
                                    {t('users.form.zones')}
                                </legend>
                                {zones.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('users.form.zones_empty')}
                                    </p>
                                ) : (
                                    zones.map((zone) => (
                                        <div
                                            key={zone.id}
                                            className="flex items-center space-x-3"
                                        >
                                            <Checkbox
                                                id={`zone-${zone.id}`}
                                                name="zone_ids[]"
                                                value={String(zone.id)}
                                                defaultChecked={user?.zone_ids?.includes(
                                                    zone.id,
                                                )}
                                            />
                                            <Label htmlFor={`zone-${zone.id}`}>
                                                {zone.name}
                                            </Label>
                                        </div>
                                    ))
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {t('users.form.zones_help')}
                                </p>
                                <InputError message={errors.zone_ids} />
                            </fieldset>
                        )}

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="password">
                                {t('users.form.password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                required={!isEdit}
                                passwordrules={passwordRules}
                            />
                            {isEdit && (
                                <p className="text-xs text-muted-foreground">
                                    {t('users.form.password_optional')}
                                </p>
                            )}
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="password_confirmation">
                                {t('users.form.password_confirmation')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required={!isEdit}
                                passwordrules={passwordRules}
                            />
                        </div>

                        {isEdit && (
                            <div className="col-span-full grid gap-2">
                                <div className="flex items-center space-x-3">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    />
                                    <Checkbox
                                        id="is_active"
                                        name="is_active"
                                        value="1"
                                        defaultChecked={user.is_active}
                                        disabled={isSelf}
                                    />
                                    <Label htmlFor="is_active">
                                        {t('users.form.is_active')}
                                    </Label>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {t('users.form.is_active_help')}
                                </p>
                                <InputError message={errors.is_active} />
                            </div>
                        )}

                        <SaveBar
                            dirty={isDirty}
                            processing={processing}
                            isNew={!user}
                        >
                            {submitLabel}
                        </SaveBar>
                    </>
                );
            }}
        </Form>
    );
}
