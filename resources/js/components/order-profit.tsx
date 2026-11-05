import { HelpHint } from '@/components/hint';
import { Badge } from '@/components/ui/badge';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

export type OrderProfitData = {
    theoretical_margin: number;
    actual_purchase: number;
    logistics: number;
    real_margin: number;
    projected_margin: number;
    gap: number;
    expenses: {
        id: number;
        type: string;
        amount: number;
        incurred_on: string;
        cancelled: boolean;
    }[];
};

export default function OrderProfit({ profit }: { profit: OrderProfitData }) {
    const { t } = useTranslation();
    const { money, date } = useFormat();

    const figures: [string, number][] = [
        ['theoretical', profit.theoretical_margin],
        ['actual_purchase', profit.actual_purchase],
        ['logistics', profit.logistics],
        ['real', profit.real_margin],
        ['projected', profit.projected_margin],
        ['gap', profit.gap],
    ];

    return (
        <section className="space-y-3">
            <h2 className="text-base font-semibold">
                {t('expenses.profit.order.title')}
            </h2>
            <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {figures.map(([key, value]) => (
                    <div
                        key={key}
                        className="rounded-lg border border-border p-3"
                    >
                        <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            {t(`expenses.profit.order.${key}`)}
                            <HelpHint
                                label={t(`expenses.profit.order.help.${key}`)}
                            />
                        </dt>
                        <dd className="mt-1 text-lg font-semibold tabular-nums">
                            {money(value)}
                        </dd>
                    </div>
                ))}
            </dl>

            <h3 className="text-sm text-muted-foreground">
                {t('expenses.profit.order.expenses')}
            </h3>
            {profit.expenses.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('expenses.profit.order.no_expenses')}
                </p>
            ) : (
                <ul className="divide-y divide-border rounded-lg border border-border text-sm">
                    {profit.expenses.map((expense) => (
                        <li
                            key={expense.id}
                            className="flex items-center justify-between gap-2 px-4 py-2"
                        >
                            <span>
                                {expense.type} · {date(expense.incurred_on)}
                                {expense.cancelled && (
                                    <Badge variant="secondary" className="ml-2">
                                        {t('expenses.state_cancelled')}
                                    </Badge>
                                )}
                            </span>
                            <span>{money(expense.amount)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
