import { useHttp } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/ZoneController';
import { Hint } from '@/components/hint';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export type NewZone = { id: number; name: string };

/**
 * Adds a sub-prefecture from where it is needed, without leaving the page or losing what was typed:
 * a button beside the choice opens a small dialog, and the new sub-prefecture is handed back to be
 * chosen straight away.
 */
export default function ZoneQuickAdd({
    onAdded,
}: {
    onAdded: (zone: NewZone) => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const http = useHttp<{ name: string; code: string }, NewZone>({
        name: '',
        code: '',
    });

    const submit = (): void => {
        http.post(store.url(), {
            onSuccess: (zone) => {
                onAdded(zone);
                toast.success(
                    t('clients.form.zone_added', { name: zone.name }),
                );
                http.resetAndClearErrors();
                setOpen(false);
            },
        }).catch(() => undefined);
    };

    const submitOnEnter = (event: KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submit();
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    http.resetAndClearErrors();
                }
            }}
        >
            <Hint label={t('clients.form.add_zone')}>
                <DialogTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label={t('clients.form.add_zone')}
                    >
                        <Plus />
                    </Button>
                </DialogTrigger>
            </Hint>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('clients.form.add_zone')}</DialogTitle>
                    <DialogDescription>
                        {t('clients.form.add_zone_description')}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="new-zone-name">
                            {t('catalog.zones.form.name')}
                        </Label>
                        <Input
                            id="new-zone-name"
                            value={http.data.name}
                            onChange={(event) =>
                                http.setData('name', event.target.value)
                            }
                            onKeyDown={submitOnEnter}
                            autoFocus
                            required
                        />
                        <InputError message={http.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="new-zone-code">
                            {t('catalog.zones.form.code')}
                        </Label>
                        <Input
                            id="new-zone-code"
                            value={http.data.code}
                            onChange={(event) =>
                                http.setData('code', event.target.value)
                            }
                            onKeyDown={submitOnEnter}
                        />
                        <InputError message={http.errors.code} />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        {t('common.actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="success"
                        onClick={submit}
                        disabled={http.processing}
                    >
                        {t('common.actions.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
