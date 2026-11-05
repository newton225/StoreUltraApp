import { router } from '@inertiajs/react';
import { useState } from 'react';
import { link } from '@/actions/App/Http/Controllers/CashCloseController';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
import { Checkbox } from '@/components/ui/checkbox';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

export type UnlinkedExpense = {
    id: number;
    type: string;
    amount: number;
    incurred_on: string;
    notes: string | null;
};

/**
 * Attaches older logistics costs, recorded before the link with the cash box existed, to the cash box. Nothing
 * is ticked by default: the office ticks only the costs that were really paid in cash (playbook UI-115).
 */
export default function CashLinkExpenses({
    expenses,
}: {
    expenses: UnlinkedExpense[];
}) {
    const { t } = useTranslation();
    const { money, date } = useFormat();
    const [picked, setPicked] = useState<number[]>([]);

    if (expenses.length === 0) {
        return null;
    }

    const toggle = (id: number, checked: boolean): void =>
        setPicked((current) =>
            checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        );

    const total = expenses
        .filter((expense) => picked.includes(expense.id))
        .reduce((sum, expense) => sum + expense.amount, 0);

    return (
        <CollapsiblePanel title={t('cash.link.open')} defaultOpen={false}>
            <div className="max-w-2xl space-y-4">
                <p className="text-sm text-muted-foreground">
                    {t('cash.link.description')}
                </p>

                <div className="flex items-center justify-between text-sm">
                    <button
                        type="button"
                        className="text-primary underline-offset-4 hover:underline"
                        onClick={() =>
                            setPicked(
                                picked.length === expenses.length
                                    ? []
                                    : expenses.map((expense) => expense.id),
                            )
                        }
                    >
                        {picked.length === expenses.length
                            ? t('cash.link.select_none')
                            : t('cash.link.select_all')}
                    </button>
                    <span className="text-muted-foreground">
                        {t('cash.link.selected', {
                            count: picked.length,
                            amount: money(total),
                        })}
                    </span>
                </div>

                <ul className="max-h-80 divide-y divide-border overflow-y-auto rounded-lg border border-border text-sm">
                    {expenses.map((expense) => (
                        <li key={expense.id}>
                            <label className="flex cursor-pointer items-center gap-3 px-4 py-2">
                                <Checkbox
                                    checked={picked.includes(expense.id)}
                                    onCheckedChange={(checked) =>
                                        toggle(expense.id, checked === true)
                                    }
                                />
                                <span className="flex-1">
                                    <span className="font-medium">
                                        {expense.type}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' · '}
                                        {date(expense.incurred_on)}
                                        {expense.notes
                                            ? ` · ${expense.notes}`
                                            : ''}
                                    </span>
                                </span>
                                <span className="font-medium whitespace-nowrap">
                                    {money(expense.amount)}
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>

                <ConfirmAction
                    label={t('cash.link.confirm')}
                    title={t('cash.link.confirm_title')}
                    description={t('cash.link.confirm_body', {
                        count: picked.length,
                        amount: money(total),
                    })}
                    disabled={picked.length === 0}
                    onConfirm={() =>
                        router.post(
                            link.url(),
                            { expense_ids: picked },
                            {
                                preserveScroll: true,
                                onSuccess: () => setPicked([]),
                            },
                        )
                    }
                />
            </div>
        </CollapsiblePanel>
    );
}
