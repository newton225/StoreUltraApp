import { router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/OrderDiscountController';
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
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

export type OrderDiscountData = {
    id: number;
    amount: number;
    reason: string;
    total_before: number;
    total_after: number;
    user: string | null;
    at: string | null;
};

/**
 * The button and the form that lower the price of an order: an amount, the reason and the password of the person
 * granting it. The server refuses an amount above what is left to pay.
 */
export default function OrderDiscount({
    orderId,
    maximum,
}: {
    orderId: number;
    maximum: number;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const [open, setOpen] = useState(false);
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');
    const [password, setPassword] = useState('');
    const [sending, setSending] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = (): void => {
        router.post(
            store.url(orderId),
            { amount, reason, password },
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                onError: (received) => setErrors(received),
                onSuccess: () => {
                    setErrors({});
                    setOpen(false);
                    setAmount('');
                    setReason('');
                    setPassword('');
                },
            },
        );
    };

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setOpen(true)}
                data-test="order-discount-open"
            >
                {t('orders.discount.open')}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('orders.discount.title')}</DialogTitle>
                        <DialogDescription>
                            {t('orders.discount.help', {
                                max: money(maximum),
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="discount-amount">
                                {t('orders.discount.amount')}
                                <span className="text-destructive"> *</span>
                            </Label>
                            <Input
                                id="discount-amount"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={maximum}
                                required
                                value={amount}
                                onChange={(event) =>
                                    setAmount(event.target.value)
                                }
                            />
                            <InputError message={errors.amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="discount-reason">
                                {t('orders.discount.reason')}
                                <span className="text-destructive"> *</span>
                            </Label>
                            <Textarea
                                id="discount-reason"
                                rows={3}
                                required
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                            />
                            <InputError message={errors.reason} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="discount-password">
                                {t('orders.discount.password')}
                                <span className="text-destructive"> *</span>
                            </Label>
                            <Input
                                id="discount-password"
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
                            {t('orders.discount.back')}
                        </Button>
                        <Button
                            variant="success"
                            disabled={sending}
                            onClick={submit}
                        >
                            {t('orders.discount.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
