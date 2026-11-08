import { router } from '@inertiajs/react';
import type { VariantProps } from 'class-variance-authority';
import { useEffect, useRef, useState } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

type ButtonVariant = VariantProps<typeof buttonVariants>['variant'];
type ButtonSize = VariantProps<typeof buttonVariants>['size'];

/**
 * A button that asks before doing something that is not easily undone (playbook UI-20 to UI-22). The window
 * names the consequence, puts the focus on "Cancel" and disables the action while it is being sent: from the
 * confirmation until the server has answered, the button on the page is greyed and says it is working
 * (playbook UI-123).
 *
 * Inside a form, leave `onConfirm` out: the button first checks the form (required fields), then sends it
 * once the user has confirmed. Outside a form, pass `onConfirm`.
 */
export default function ConfirmAction({
    label,
    title,
    description,
    confirmLabel,
    variant = 'success',
    size,
    destructive = false,
    disabled = false,
    className,
    onConfirm,
}: {
    label: string;
    title: string;
    description: string;
    /** Defaults to the label of the button. */
    confirmLabel?: string;
    variant?: ButtonVariant;
    size?: ButtonSize;
    destructive?: boolean;
    disabled?: boolean;
    className?: string;
    onConfirm?: () => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useRef<HTMLFormElement | null>(null);
    const [sending, setSending] = useState(false);
    const stopWatching = useRef<(() => void) | null>(null);

    useEffect(() => () => stopWatching.current?.(), []);

    // Listening starts at the confirmation, before the request is made, so that its start is never missed.
    const watchSending = (): void => {
        stopWatching.current?.();
        setSending(true);

        let started = false;
        const release = (): void => {
            stopWatching.current?.();
            stopWatching.current = null;
            setSending(false);
        };
        const stopStart = router.on('start', () => {
            started = true;
        });
        const stopFinish = router.on('finish', () => {
            if (started) {
                release();
            }
        });
        // Nothing was sent (the action did not make a request): the button is free again.
        const timer = window.setTimeout(() => {
            if (!started) {
                release();
            }
        }, 1500);

        stopWatching.current = () => {
            stopStart();
            stopFinish();
            window.clearTimeout(timer);
        };
    };

    return (
        <>
            <Button
                type="button"
                data-test="confirm-action"
                variant={variant}
                size={size}
                className={className}
                disabled={disabled || sending}
                onClick={(event) => {
                    const parent = event.currentTarget.form;

                    if (parent && !parent.reportValidity()) {
                        return;
                    }

                    form.current = parent;
                    setOpen(true);
                }}
            >
                {sending ? t('common.form.saving') : label}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            autoFocus
                            onClick={() => setOpen(false)}
                        >
                            {t('common.actions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            data-test="confirm-action-confirm"
                            variant={destructive ? 'destructive' : 'success'}
                            onClick={() => {
                                setOpen(false);
                                watchSending();

                                if (onConfirm) {
                                    onConfirm();
                                } else {
                                    form.current?.requestSubmit();
                                }
                            }}
                        >
                            {confirmLabel ?? label}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
