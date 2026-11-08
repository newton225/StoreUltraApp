import { Link } from '@inertiajs/react';
import { show } from '@/actions/App/Http/Controllers/LegalController';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

const documents = ['notice', 'privacy', 'terms'] as const;

type Props = {
    /** The text being read, shown as the current one. */
    current?: string;
    className?: string;
};

/**
 * The three legal texts, linked from every place that must offer them: the sign-in page, the home page and the
 * menu (playbook chapter 08). Plain links, so a reader without an account can open them.
 */
export default function LegalLinks({ current, className }: Props) {
    const { t } = useTranslation();

    return (
        <nav
            aria-label={t('navigation.legal.notice')}
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground',
                className,
            )}
        >
            {documents.map((document) => (
                <Link
                    key={document}
                    href={show.url({ document })}
                    aria-current={current === document ? 'page' : undefined}
                    className={cn(
                        'underline-offset-4 hover:text-foreground hover:underline',
                        current === document && 'font-medium text-foreground',
                    )}
                >
                    {t(`navigation.legal.${document}`)}
                </Link>
            ))}
        </nav>
    );
}
