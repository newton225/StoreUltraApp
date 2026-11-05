type TooltipItem = {
    name?: string | number;
    value?: string | number | readonly (string | number)[];
    color?: string;
    dataKey?: string | number;
};

type Props = {
    /** Given by the chart when the element is used as its tooltip. */
    active?: boolean;
    payload?: readonly TooltipItem[];
    label?: string | number;
    formatValue: (value: number) => string;
    formatLabel: (label: string) => string;
};

/**
 * What a bar says when it is hovered: the category and, for each series, its colour mark, its name
 * and its value in the language of the interface. Text keeps the text colours; the mark carries the
 * series colour.
 */
export function ChartTooltip({
    active,
    payload,
    label,
    formatValue,
    formatLabel,
}: Props) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    return (
        <div className="min-w-40 rounded-md border border-border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
            <p className="mb-1.5 font-medium">{formatLabel(String(label))}</p>
            <ul className="space-y-1">
                {payload.map((item) => (
                    <li
                        key={String(item.dataKey)}
                        className="flex items-center gap-2"
                    >
                        <span
                            aria-hidden
                            className="size-2.5 shrink-0 rounded-[3px]"
                            style={{ backgroundColor: item.color }}
                        />
                        <span className="text-muted-foreground">
                            {item.name}
                        </span>
                        <span className="ml-auto pl-4 font-medium tabular-nums">
                            {formatValue(Number(item.value))}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
