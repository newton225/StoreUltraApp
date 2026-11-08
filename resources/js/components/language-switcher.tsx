import { router, usePage } from '@inertiajs/react';
import { Hint } from '@/components/hint';
import { update } from '@/actions/App/Http/Controllers/LocaleController';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export default function LanguageSwitcher({
    className,
}: {
    className?: string;
}) {
    const { supportedLocales } = usePage().props;
    const { t, locale } = useTranslation();

    const choose = (next: string): void => {
        if (next === locale) {
            return;
        }

        router.post(
            update.url(),
            { locale: next },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div
            role="group"
            aria-label={t('common.language.label')}
            className={cn(
                'inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800',
                className,
            )}
        >
            {supportedLocales.map((code) => (
                <Hint key={code} label={t(`common.language.${code}`)}>
                    <button
                        type="button"
                        lang={code}
                        data-test={`locale-${code}`}
                        aria-pressed={code === locale}
                        onClick={() => choose(code)}
                        className={cn(
                            'rounded-md px-2.5 py-1 text-xs font-medium uppercase transition-colors',
                            code === locale
                                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                        )}
                    >
                        {code}
                    </button>
                </Hint>
            ))}
        </div>
    );
}
