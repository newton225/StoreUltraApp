import { router } from '@inertiajs/react';
import { useEffect, useId } from 'react';

/**
 * One guard for the whole page, whatever the number of forms that hold unsaved changes: a single question
 * when the user really leaves, never one per form. The forms that are dirty register here; the listeners
 * exist only while at least one of them is.
 */
const dirtyForms = new Set<string>();
let stopWatching: (() => void) | null = null;
let question = '';

const warnBeforeClosing = (event: BeforeUnloadEvent): void => {
    event.preventDefault();
};

// In development the page reloads by itself when a file of the project is saved: no question then.
if (import.meta.hot) {
    import.meta.hot.on('vite:beforeFullReload', () => {
        window.removeEventListener('beforeunload', warnBeforeClosing);
    });
}

function install(): void {
    window.addEventListener('beforeunload', warnBeforeClosing);

    const stopVisits = router.on('before', (event) => {
        const visit = event.detail.visit;

        // Not a way of leaving: sending a form, a page prefetched when a link is hovered, or the same
        // page loaded again (filters, reloads).
        if (
            visit.method !== 'get' ||
            visit.prefetch ||
            new URL(visit.url, window.location.origin).pathname ===
                window.location.pathname
        ) {
            return;
        }

        if (!window.confirm(question)) {
            event.preventDefault();
        }
    });

    stopWatching = () => {
        window.removeEventListener('beforeunload', warnBeforeClosing);
        stopVisits();
    };
}

function uninstall(): void {
    stopWatching?.();
    stopWatching = null;
}

/**
 * Ask before the user leaves the page while this form holds unsaved changes. A form being sent does not
 * count as holding changes any more.
 */
export function useLeaveGuard(dirty: boolean, message: string): void {
    const id = useId();

    useEffect(() => {
        if (!dirty) {
            return;
        }

        question = message;
        dirtyForms.add(id);

        if (dirtyForms.size === 1) {
            install();
        }

        return () => {
            dirtyForms.delete(id);

            if (dirtyForms.size === 0) {
                uninstall();
            }
        };
    }, [dirty, id, message]);
}
