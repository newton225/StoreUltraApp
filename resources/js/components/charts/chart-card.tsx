import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type ChartTable = {
    columns: string[];
    rows: (string | number)[][];
};

type Props = {
    title: string;
    description?: string;
    /** The same figures as the chart, for whoever reads a table better, or cannot see colours. */
    table: ChartTable;
    /** Nothing to plot: the chart shows an empty state and there is no table to switch to. */
    empty?: boolean;
    children: ReactNode;
    className?: string;
};

/**
 * A chart in its card: a title, what it shows, the chart, and a switch to read the same figures as a
 * table (a chart is never the only way to get at its numbers).
 */
export function ChartCard({
    title,
    description,
    table,
    empty = false,
    children,
    className,
}: Props) {
    const { t } = useTranslation();
    const [asTable, setAsTable] = useState(false);

    return (
        <section
            className={cn(
                'space-y-4 rounded-xl border border-border bg-card p-5',
                className,
            )}
        >
            <header className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <h3 className="text-sm leading-none font-medium">
                        {title}
                    </h3>
                    {description && (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {!empty && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="shrink-0 text-muted-foreground"
                        onClick={() => setAsTable((current) => !current)}
                        aria-pressed={asTable}
                    >
                        {asTable
                            ? t('dashboard.charts.view_chart')
                            : t('dashboard.charts.view_table')}
                    </Button>
                )}
            </header>

            {asTable && !empty ? (
                <div className="max-h-72 overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-xs text-muted-foreground">
                            <tr>
                                {table.columns.map((column, index) => (
                                    <th
                                        key={column}
                                        className={cn(
                                            'px-2 py-1.5 font-medium',
                                            index > 0 && 'text-right',
                                        )}
                                    >
                                        {column}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {table.rows.map((row, rowIndex) => (
                                <tr
                                    key={rowIndex}
                                    className="border-t border-border"
                                >
                                    {row.map((cell, index) => (
                                        <td
                                            key={index}
                                            className={cn(
                                                'px-2 py-1.5',
                                                index > 0 &&
                                                    'text-right tabular-nums',
                                            )}
                                        >
                                            {cell}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                children
            )}
        </section>
    );
}
