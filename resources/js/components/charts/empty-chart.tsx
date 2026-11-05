const PLACEHOLDER_BARS = [38, 62, 48, 82, 56, 72];

/**
 * What a chart shows when the period has nothing to plot: the faint shape of the bars it will draw and
 * a sentence that says why it is empty, so a new dashboard still shows where the charts will be.
 */
export function EmptyChart({
    message,
    height = 260,
}: {
    message: string;
    height?: number;
}) {
    return (
        <div
            className="relative flex items-center justify-center rounded-lg border border-dashed border-border"
            style={{ height }}
        >
            <div
                aria-hidden
                className="absolute inset-x-6 top-10 bottom-6 flex items-end justify-between gap-3 opacity-50"
            >
                {PLACEHOLDER_BARS.map((percent, index) => (
                    <span
                        key={index}
                        className="flex-1 rounded-t-md bg-muted"
                        style={{ height: `${percent}%` }}
                    />
                ))}
            </div>
            <p className="relative z-10 max-w-xs rounded-md bg-card/90 px-3 py-1.5 text-center text-sm text-muted-foreground">
                {message}
            </p>
        </div>
    );
}
