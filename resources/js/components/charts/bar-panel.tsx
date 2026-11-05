import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartTooltip } from '@/components/charts/chart-tooltip';
import { useTranslation } from '@/hooks/use-translation';

export type Series = { key: string; label: string; color: string };

type Row = Record<string, string | number | null>;

type Props = {
    data: Row[];
    categoryKey: string;
    series: Series[];
    /** Horizontal bars, for categories with long names; vertical columns for weeks. */
    horizontal?: boolean;
    formatValue: (value: number) => string;
    formatCategory?: (category: string) => string;
    /** The title of the tooltip, when it should say more than the axis does. */
    formatTooltipLabel?: (category: string) => string;
    height?: number;
    ariaLabel: string;
};

const AXIS_TICK = { fill: 'var(--muted-foreground)', fontSize: 12 };

/**
 * Bars for one or two series. Thin marks with a rounded end and a small gap between them, a hairline
 * grid, axes that stay in the background, a legend whenever there are two series, and a tooltip. The
 * motion is short, and absent for whoever asked for less of it.
 */
export function BarPanel({
    data,
    categoryKey,
    series,
    horizontal = false,
    formatValue,
    formatCategory = (category) => category,
    formatTooltipLabel,
    height = 260,
    ariaLabel,
}: Props) {
    const { locale } = useTranslation();
    const compact = new Intl.NumberFormat(locale, {
        notation: 'compact',
        maximumFractionDigits: 1,
    });
    const reducedMotion =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const valueAxis = (
        <>
            {horizontal ? (
                <XAxis
                    type="number"
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                    tick={AXIS_TICK}
                    tickFormatter={(value: number) => compact.format(value)}
                />
            ) : (
                <YAxis
                    allowDecimals={false}
                    width={48}
                    tickLine={false}
                    axisLine={false}
                    tick={AXIS_TICK}
                    tickFormatter={(value: number) => compact.format(value)}
                />
            )}
        </>
    );

    const categoryAxis = horizontal ? (
        <YAxis
            type="category"
            dataKey={categoryKey}
            width={110}
            tickLine={false}
            axisLine={false}
            tick={AXIS_TICK}
            tickFormatter={formatCategory}
        />
    ) : (
        <XAxis
            dataKey={categoryKey}
            tickLine={false}
            axisLine={{ stroke: 'var(--border)' }}
            tick={AXIS_TICK}
            tickFormatter={formatCategory}
            tickMargin={8}
        />
    );

    return (
        <div role="img" aria-label={ariaLabel}>
            <ResponsiveContainer width="100%" height={height}>
                <BarChart
                    data={data}
                    layout={horizontal ? 'vertical' : 'horizontal'}
                    margin={{ top: 4, right: 8, bottom: 0, left: 0 }}
                    barGap={2}
                    barCategoryGap="28%"
                >
                    <CartesianGrid
                        stroke="var(--border)"
                        vertical={horizontal}
                        horizontal={!horizontal}
                    />
                    {categoryAxis}
                    {valueAxis}
                    <Tooltip
                        cursor={{ fill: 'var(--muted)', fillOpacity: 0.5 }}
                        content={
                            <ChartTooltip
                                formatValue={formatValue}
                                formatLabel={
                                    formatTooltipLabel ?? formatCategory
                                }
                            />
                        }
                    />
                    {series.length > 1 && (
                        <Legend
                            verticalAlign="top"
                            align="left"
                            iconType="square"
                            iconSize={10}
                            height={28}
                            formatter={(value: string) => (
                                <span className="text-xs text-foreground">
                                    {value}
                                </span>
                            )}
                        />
                    )}
                    {series.map((item) => (
                        <Bar
                            key={item.key}
                            dataKey={item.key}
                            name={item.label}
                            fill={item.color}
                            radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
                            maxBarSize={28}
                            isAnimationActive={!reducedMotion}
                            animationDuration={500}
                        />
                    ))}
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
