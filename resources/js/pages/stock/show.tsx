import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    adjust,
    index,
    show,
} from '@/actions/App/Http/Controllers/StockController';
import ConfirmAction from '@/components/confirm-action';
import { FieldLabel } from '@/components/field-label';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { formGrid } from '@/lib/form-layout';

type Movement = {
    id: number;
    type: 'receipt' | 'allocation' | 'release' | 'adjustment';
    quantity: number;
    unit_cost: number;
    reason: string | null;
    user: string | null;
    at: string;
};

type Props = {
    product: { id: number; name: string; unit: string; pack_size: number };
    levels: { on_hand: number; average_cost: number; value: number };
    movements: Movement[];
    canAdjust: boolean;
};

const typeVariant = {
    receipt: 'success',
    allocation: 'info',
    release: 'warning',
    adjustment: 'warning',
} as const;

export default function StockShow({
    product,
    levels,
    movements,
    canAdjust,
}: Props) {
    const { t } = useTranslation();
    const { money, date } = useFormat();

    setLayoutProps({
        breadcrumbs: [
            { title: t('stock.title'), href: index() },
            { title: product.name, href: show(product.id) },
        ],
    });

    return (
        <>
            <Head title={product.name} />

            <div className="max-w-4xl space-y-8 p-4">
                <div className="space-y-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={index()}>
                            <ArrowLeft aria-hidden />
                            {t('stock.back')}
                        </Link>
                    </Button>
                    <Heading title={product.name} />
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    {(
                        [
                            ['on_hand', `${levels.on_hand} ${product.unit}`],
                            ['average_cost', money(levels.average_cost)],
                            ['value', money(levels.value)],
                        ] as const
                    ).map(([key, value]) => (
                        <div
                            key={key}
                            className="rounded-lg border border-border p-4"
                        >
                            <div className="text-sm text-muted-foreground">
                                {t(`stock.summary.${key}`)}
                            </div>
                            <div className="text-lg font-semibold">{value}</div>
                        </div>
                    ))}
                </div>

                {canAdjust && (
                    <section className="space-y-4">
                        <Heading
                            variant="small"
                            title={t('stock.adjust.title')}
                            description={t('stock.adjust.description')}
                        />
                        <Form
                            {...adjust.form(product.id)}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className={formGrid}
                        >
                            {({ processing, errors: formErrors }) => {
                                const errors: Record<
                                    string,
                                    string | undefined
                                > = formErrors;

                                return (
                                    <>
                                        <div className="grid gap-2 md:col-span-3">
                                            <FieldLabel
                                                htmlFor="stock-quantity"
                                                help={t(
                                                    'stock.adjust.quantity_help',
                                                )}
                                            >
                                                {t('stock.adjust.quantity')}
                                            </FieldLabel>
                                            <Input
                                                id="stock-quantity"
                                                name="quantity"
                                                type="number"
                                                step={1}
                                                required
                                            />
                                            <InputError
                                                message={errors.quantity}
                                            />
                                        </div>

                                        <div className="grid gap-2 md:col-span-3">
                                            <FieldLabel
                                                htmlFor="stock-cost"
                                                help={t(
                                                    'stock.adjust.unit_cost_help',
                                                )}
                                            >
                                                {t('stock.adjust.unit_cost')}
                                            </FieldLabel>
                                            <Input
                                                id="stock-cost"
                                                name="unit_cost"
                                                type="number"
                                                min={0}
                                                step="any"
                                            />
                                            <InputError
                                                message={errors.unit_cost}
                                            />
                                        </div>

                                        <div className="grid gap-2 md:col-span-6">
                                            <Label htmlFor="stock-reason">
                                                {t('stock.adjust.reason')}
                                            </Label>
                                            <Input
                                                id="stock-reason"
                                                name="reason"
                                                maxLength={500}
                                                required
                                            />
                                            <InputError
                                                message={errors.reason}
                                            />
                                        </div>

                                        <div className="grid gap-2 md:col-span-3">
                                            <Label htmlFor="stock-password">
                                                {t('stock.adjust.password')}
                                            </Label>
                                            <Input
                                                id="stock-password"
                                                name="password"
                                                type="password"
                                                autoComplete="current-password"
                                                required
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <div className="flex items-end md:col-span-3">
                                            <ConfirmAction
                                                label={t('stock.adjust.submit')}
                                                title={t(
                                                    'stock.adjust.confirm_title',
                                                )}
                                                description={t(
                                                    'stock.adjust.confirm_body',
                                                )}
                                                disabled={processing}
                                            />
                                        </div>
                                    </>
                                );
                            }}
                        </Form>
                    </section>
                )}

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('stock.movements_title')}
                    />
                    {movements.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('stock.movements_empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            {t('stock.columns.date')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('stock.columns.type')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('stock.columns.quantity')}
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            {t('stock.columns.unit_cost')}
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            {t('stock.columns.reason')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {movements.map((movement) => (
                                        <tr
                                            key={movement.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2 whitespace-nowrap">
                                                {date(movement.at)}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Badge
                                                    variant={
                                                        typeVariant[
                                                            movement.type
                                                        ]
                                                    }
                                                >
                                                    {t(
                                                        `stock.types.${movement.type}`,
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-2 text-right whitespace-nowrap">
                                                {movement.quantity > 0
                                                    ? `+${movement.quantity}`
                                                    : movement.quantity}
                                            </td>
                                            <td className="px-4 py-2 text-right whitespace-nowrap">
                                                {money(movement.unit_cost)}
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {[
                                                    movement.reason,
                                                    movement.user,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
