import { router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderCancellationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Cancels, in one go, the orders ticked in the list: one reason for all, and the password of the person doing it.
 * The server leaves alone the orders that cannot be cancelled any more and says how many.
 */
export default function OrderCancelBatch({
    orderIds,
    onDone,
}: {
    orderIds: number[];
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [password, setPassword] = useState('');
    const [sending, setSending] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = (): void => {
        router.post(
            store.url(),
            { orders: orderIds, reason, password },
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                onError: (received) => setErrors(received),
                onSuccess: () => {
                    setErrors({});
                    setOpen(false);
                    setReason('');
                    setPassword('');
                    onDone();
                },
            },
        );
    };

    return (
        <>
            <Button
                size="sm"
                variant="destructive"
                onClick={() => setOpen(true)}
                data-test="order-cancel-batch-open"
            >
                {t('orders.cancel_batch.open', { count: orderIds.length })}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('orders.cancel_batch.title', {
                                count: orderIds.length,
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('orders.cancel_batch.help')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="cancel-batch-reason">
                                {t('orders.cancel_batch.reason')}
                                <span className="text-destructive"> *</span>
                            </Label>
                            <Textarea
                                id="cancel-batch-reason"
                                rows={3}
                                required
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                            />
                            <InputError message={errors.reason} />
                            <InputError message={errors.orders} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="cancel-batch-password">
                                {t('orders.cancel_batch.password')}
                                <span className="text-destructive"> *</span>
                            </Label>
                            <Input
                                id="cancel-batch-password"
                                type="password"
                                required
                                autoComplete="current-password"
                                value={password}
                                onChange={(event) =>
                                    setPassword(event.target.value)
                                }
                            />
                            <InputError message={errors.password} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            {t('orders.cancel_batch.back')}
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={sending}
                            onClick={submit}
                        >
                            {t('orders.cancel_batch.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
