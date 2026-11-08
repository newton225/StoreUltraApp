import { Head, router, setLayoutProps } from '@inertiajs/react';
import { CircleAlert, CircleCheck, TriangleAlert } from 'lucide-react';
import { index } from '@/actions/App/Http/Controllers/HealthController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { useVisitBusy } from '@/hooks/use-visit-busy';

type Check = {
    key: string;
    status: 'ok' | 'warning' | 'error';
    detail: string;
    params: Record<string, string | number>;
    lines: string[];
};

type Props = {
    checks: Check[];
    summary: { errors: number; warnings: number };
    checked_at: string;
};

const look = {
    ok: {
        badge: 'success',
        icon: CircleCheck,
        tone: 'text-success-text',
        border: 'border-border',
    },
    warning: {
        badge: 'warning',
        icon: TriangleAlert,
        tone: 'text-warning-text',
        border: 'border-warning/40',
    },
    error: {
        badge: 'danger',
        icon: CircleAlert,
        tone: 'text-destructive-text',
        border: 'border-destructive/40',
    },
} as const;

export default function HealthIndex({
    checks,
    summary,
    checked_at: checkedAt,
}: Props) {
    const { t, locale } = useTranslation();
    const busy = useVisitBusy();

    setLayoutProps({
        breadcrumbs: [{ title: t('health.title'), href: index() }],
    });

    const allGood = summary.errors === 0 && summary.warnings === 0;

    return (
        <>
            <Head title={t('health.head')} />

            <div className="max-w-4xl space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={t('health.title')}
                        description={t('health.description')}
                    />
                    <Button
                        variant="outline"
                        className="shrink-0"
                        disabled={busy}
                        onClick={() => router.reload()}
                    >
                        {t('health.recheck')}
                    </Button>
                </div>

                <div
                    className={`flex flex-wrap items-center justify-between gap-2 rounded-lg border p-4 ${
                        allGood
                            ? 'border-success/30 bg-success/10'
                            : summary.errors > 0
                              ? 'border-destructive/40 bg-destructive/10'
                              : 'border-warning/40 bg-warning/10'
                    }`}
                    role="status"
                >
                    <p className="text-sm font-medium">
                        {allGood
                            ? t('health.summary.ok')
                            : t('health.summary.attention', {
                                  errors: summary.errors,
                                  warnings: summary.warnings,
                              })}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('health.checked_at', {
                            time: new Date(checkedAt).toLocaleTimeString(
                                locale,
                                { hour: '2-digit', minute: '2-digit' },
                            ),
                        })}
                    </p>
                </div>

                <ul className="space-y-3">
                    {checks.map((check) => {
                        const style = look[check.status];
                        const Icon = style.icon;

                        return (
                            <li
                                key={check.key}
                                className={`rounded-lg border p-4 ${style.border}`}
                            >
                                <div className="flex items-start gap-3">
                                    <Icon
                                        className={`mt-0.5 size-5 shrink-0 ${style.tone}`}
                                        aria-hidden="true"
                                    />
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-base font-semibold">
                                                {t(
                                                    `health.checks.${check.key}.title`,
                                                )}
                                            </h2>
                                            <Badge variant={style.badge}>
                                                {t(
                                                    `health.status.${check.status}`,
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="text-sm">
                                            {t(
                                                `health.checks.${check.key}.${check.detail}`,
                                                check.params,
                                            )}
                                        </p>

                                        {check.status !== 'ok' && (
                                            <p className="text-sm text-muted-foreground">
                                                <span className="font-medium text-foreground">
                                                    {t('health.todo')} :
                                                </span>{' '}
                                                {t(
                                                    `health.checks.${check.key}.todo`,
                                                )}
                                            </p>
                                        )}

                                        {check.lines.length > 0 && (
                                            <details className="pt-1 text-sm">
                                                <summary className="cursor-pointer text-muted-foreground">
                                                    {t('health.details')}
                                                </summary>
                                                <ul className="mt-2 list-disc space-y-1 pl-5 text-xs text-muted-foreground">
                                                    {check.lines.map((line) => (
                                                        <li key={line}>
                                                            {line}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </details>
                                        )}
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </>
    );
}
