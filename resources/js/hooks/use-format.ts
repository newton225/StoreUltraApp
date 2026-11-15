import { useTranslation } from '@/hooks/use-translation';

/**
 * A number with a wide, non-breaking space between the thousands. French writes it with a narrow one,
 * which is hard to see on a screen: amounts such as 2 985 900 read as 2985900.
 */
function groupedNumber(format: Intl.NumberFormat, amount: number): string {
    return format
        .formatToParts(amount)
        .map((part) => (part.type === 'group' ? '\u00A0' : part.value))
        .join('');
}

/**
 * Format amounts and dates in the language chosen in the interface, not the one of the device.
 */
export function useFormat() {
    const { t, locale } = useTranslation();

    const numberFormat = new Intl.NumberFormat(locale, {
        maximumFractionDigits: 2,
    });
    const dateFormat = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    });

    return {
        money: (amount: number): string =>
            `${locale === 'fr' ? groupedNumber(numberFormat, amount) : numberFormat.format(amount)} ${t('catalog.currency')}`,
        date: (value: string): string => dateFormat.format(new Date(value)),
    };
}
