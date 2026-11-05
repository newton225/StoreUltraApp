import { usePage } from '@inertiajs/react';

type Params = Record<string, string | number>;

export type Translate = (key: string, params?: Params) => string;

/**
 * Translate a dotted key (`group.sub.key`) using the groups shared by the server.
 * A missing key renders the key itself so that the gap is visible on screen.
 */
export function useTranslation(): { t: Translate; locale: string } {
    const { translations, locale } = usePage().props;

    const t: Translate = (key, params = {}) => {
        const [group, ...path] = key.split('.');
        let value: unknown = translations?.[group];

        for (const segment of path) {
            value =
                typeof value === 'object' && value !== null
                    ? Reflect.get(value, segment)
                    : undefined;
        }

        if (typeof value !== 'string') {
            return key;
        }

        return Object.entries(params).reduce(
            (text, [name, replacement]) =>
                text.replaceAll(`:${name}`, String(replacement)),
            value,
        );
    };

    return { t, locale };
}
