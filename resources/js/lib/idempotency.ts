/**
 * A random key for one submission of a form. Sent with the request, it lets the server recognise a
 * replay of the same submission (double click, network retry) and record it only once.
 */
export function newIdempotencyKey(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');
}
