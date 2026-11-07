import { Form } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/CashCloseController';
import CollapsiblePanel from '@/components/collapsible-panel';
import ConfirmAction from '@/components/confirm-action';
import ExportMenu from '@/components/export-menu';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import type { PageLinks } from '@/components/pagination';
import Pagination from '@/components/pagination';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

export type Closing = {
    id: number;
    reference: string | null;
    closed_on: string;
    expected: number;
    counted: number;
    difference: number;
    notes: string | null;
    closer: string | null;
};

/** The day after a date given as YYYY-MM-DD, as YYYY-MM-DD. */
function dayAfter(value: string): string {
    const next = new Date(`${value}T00:00:00Z`);
    next.setUTCDate(next.getUTCDate() + 1);

    return next.toISOString().slice(0, 10);
}

/**
 * Closing the cash box: the money counted against what the books expect, with the difference kept. A closed
 * day is frozen, which the panel says before it is confirmed. The closings already made are listed below.
 */
export default function CashClosing({
    closings,
    balanceToday,
    lastClosedOn,
    today,
    userName,
}: {
    closings: PageLinks & { data: Closing[] };
    balanceToday: number;
    lastClosedOn: string | null;
    today: string;
    userName: string;
}) {
    const { t } = useTranslation();
    const { money, date } = useFormat();

    return (
        <section className="space-y-3" data-tour="closing">
            <CollapsiblePanel
                title={t('cash.closing.open')}
                defaultOpen={false}
            >
                <Form
                    {...store.form()}
                    className="max-w-xl space-y-4"
                    resetOnSuccess
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {t('cash.closing.description')}
                            </p>
                            <p className="text-sm">
                                {t('cash.closing.expected_today', {
                                    amount: money(balanceToday),
                                })}
                            </p>
                            <div className="grid items-start gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <FieldLabel
                                        htmlFor="closing-date"
                                        help={t('cash.closing.date_help')}
                                    >
                                        {t('cash.closing.date')}
                                    </FieldLabel>
                                    <Input
                                        id="closing-date"
                                        name="closed_on"
                                        type="date"
                                        max={today}
                                        min={
                                            lastClosedOn
                                                ? dayAfter(lastClosedOn)
                                                : undefined
                                        }
                                        defaultValue={today}
                                        required
                                    />
                                    <InputError message={errors.closed_on} />
                                </div>
                                <div className="grid gap-2">
                                    <FieldLabel
                                        htmlFor="closing-counted"
                                        help={t('cash.closing.counted_help')}
                                    >
                                        {t('cash.closing.counted')}
                                    </FieldLabel>
                                    <Input
                                        id="closing-counted"
                                        name="counted_amount"
                                        type="number"
                                        min={0}
                                        step={1}
                                        inputMode="numeric"
                                        required
                                    />
                                    <InputError
                                        message={errors.counted_amount}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="closing-notes">
                                    {t('cash.closing.notes')}
                                </Label>
                                <Textarea
                                    rows={3}
                                    id="closing-notes"
                                    name="notes"
                                    maxLength={1000}
                                />
                                <InputError message={errors.notes} />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t('cash.closing.recorded_note', {
                                    name: userName,
                                })}
                            </p>
                            <ConfirmAction
                                label={t('cash.closing.submit')}
                                title={t('cash.closing.confirm_title')}
                                description={t('cash.closing.confirm_body')}
                                disabled={processing}
                            />
                        </>
                    )}
                </Form>
            </CollapsiblePanel>

            {closings.data.length > 0 && (
                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold">
                            {t('cash.closing.list_title')}
                        </h2>
                        <ExportMenu view="closings" />
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    {[
                                        'reference',
                                        'date',
                                        'expected',
                                        'counted',
                                        'difference',
                                        'by',
                                    ].map((column) => (
                                        <th
                                            key={column}
                                            className={
                                                [
                                                    'expected',
                                                    'counted',
                                                    'difference',
                                                ].includes(column)
                                                    ? 'px-4 py-2 text-right font-medium'
                                                    : 'px-4 py-2 font-medium'
                                            }
                                        >
                                            {t(
                                                `cash.closing.columns.${column}`,
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {closings.data.map((closing) => (
                                    <tr
                                        key={closing.id}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="px-4 py-2">
                                            <div>{closing.reference}</div>
                                            {closing.notes && (
                                                <div className="max-w-xs text-xs text-muted-foreground">
                                                    {closing.notes}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            {date(closing.closed_on)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {money(closing.expected)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {money(closing.counted)}
                                        </td>
                                        <td
                                            className={
                                                closing.difference === 0
                                                    ? 'px-4 py-2 text-right'
                                                    : closing.difference > 0
                                                      ? 'px-4 py-2 text-right font-medium text-green-700 dark:text-green-400'
                                                      : 'px-4 py-2 text-right font-medium text-red-700 dark:text-red-400'
                                            }
                                        >
                                            {closing.difference > 0 ? '+' : ''}
                                            {money(closing.difference)}
                                        </td>
                                        <td className="px-4 py-2">
                                            {closing.closer ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination page={closings} pageName="closings_page" />
                </div>
            )}
        </section>
    );
}
