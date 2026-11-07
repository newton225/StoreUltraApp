import { usePage } from '@inertiajs/react';

/**
 * Whether the user's profile grants an action (the values of the closed catalogue, such as `cash.record`).
 * It only decides what the screen shows: the server refuses the action anyway.
 */
export function useCanDo(action: string): boolean {
    const { auth } = usePage<{ auth: { actions: string[] } }>().props;

    return auth.actions.includes(action);
}
