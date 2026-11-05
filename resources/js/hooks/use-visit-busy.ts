import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

/**
 * Whether a request of the user is in flight (a form sent, an action posted, a page being opened). A button
 * that sends something greys itself with it, so that a second click can never send the same thing twice
 * (playbook UI-22, UI-123). Background work (pages prefetched on hover, polling) does not count: the buttons
 * of the screen must not flicker for something the user did not ask.
 */
let running = 0;
const listeners = new Set<() => void>();
let installed = false;

const notify = (): void => listeners.forEach((listener) => listener());

function install(): void {
    if (installed) {
        return;
    }

    installed = true;

    router.on('start', (event) => {
        const visit = event.detail.visit;

        if (visit.prefetch || visit.async) {
            return;
        }

        running++;
        notify();
    });

    router.on('finish', (event) => {
        const visit = event.detail.visit;

        if (visit.prefetch || visit.async) {
            return;
        }

        running = Math.max(0, running - 1);
        notify();
    });
}

const subscribe = (listener: () => void): (() => void) => {
    install();
    listeners.add(listener);

    return () => listeners.delete(listener);
};

export function useVisitBusy(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => running > 0,
        () => false,
    );
}
