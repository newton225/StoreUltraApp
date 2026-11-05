import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

const TOAST_TYPES = ['success', 'info', 'warning', 'error'];

function isFlashToast(value: unknown): value is FlashToast {
    return (
        typeof value === 'object' &&
        value !== null &&
        'type' in value &&
        typeof value.type === 'string' &&
        TOAST_TYPES.includes(value.type) &&
        'message' in value &&
        typeof value.message === 'string'
    );
}

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash: unknown =
                event instanceof CustomEvent ? event.detail?.flash : undefined;
            const data: unknown =
                typeof flash === 'object' && flash !== null && 'toast' in flash
                    ? flash.toast
                    : undefined;

            if (!isFlashToast(data)) {
                return;
            }

            toast[data.type](data.message);
        });
    }, []);
}
