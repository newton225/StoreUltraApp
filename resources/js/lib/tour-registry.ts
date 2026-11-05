/**
 * The tour of the screen being shown, so that the button in the page header can replay it. A screen with a
 * tour registers its "start" function when it appears and removes it when it leaves.
 */
type Listener = () => void;

let current: (() => void) | null = null;
const listeners = new Set<Listener>();

const notify = (): void => listeners.forEach((listener) => listener());

export const tourRegistry = {
    register: (start: () => void): (() => void) => {
        current = start;
        notify();

        return () => {
            if (current === start) {
                current = null;
                notify();
            }
        };
    },
    subscribe: (listener: Listener): (() => void) => {
        listeners.add(listener);

        return () => {
            listeners.delete(listener);
        };
    },
    snapshot: (): (() => void) | null => current,
};
