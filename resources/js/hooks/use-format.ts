import { useTranslation } from '@/hooks/use-translation';

/**
 * Format amounts and dates in the language chosen in the interface, not the one of the device.
 */
export function useFormat() {
    const { t, locale } = useTranslation();

    const numberFormat = new Intl.NumberFormat(locale, {
        maximumFractionDigits: 0,
    });
    const dateFormat = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    });

    return {
        money: (amount: number): string =>
            `${numberFormat.format(amount)} ${t('catalog.currency')}`,
        date: (value: string): string => dateFormat.format(new Date(value)),
    };
}
