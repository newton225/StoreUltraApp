import { router, usePage } from '@inertiajs/react';
import SelectField from '@/components/select-field';
import type { SortState } from '@/components/sort-header';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export type SortChoice = {
    /** The column key the server accepts; empty for the natural order of the list. */
    sort: string;
    direction: 'asc' | 'desc';
    label: string;
};

/**
 * The sort of a list that is shown as cards, so has no column headers to click (playbook UI-46): one choice in a
 * list, « Sort by ». It does what a column header does: the choice goes in the address next to the filters, the
 * page goes back to the first one, and the server sorts the whole list from the columns it accepts.
 */
export default function SortSelect({
    id,
    state,
    choices,
}: {
    id: string;
    state: SortState;
    choices: SortChoice[];
}) {
    const { t } = useTranslation();
    const { url } = usePage();

    const encode = (sort: string, direction: string): string =>
        sort === '' ? '' : `${sort}:${direction}`;

    const change = (value: string): void => {
        const [sort, direction] = value.split(':');
        const target = new URL(url, window.location.origin);

        if (sort === '') {
            target.searchParams.delete('sort');
            target.searchParams.delete('direction');
        } else {
            target.searchParams.set('sort', sort);
            target.searchParams.set('direction', direction ?? 'asc');
        }

        target.searchParams.delete('page');

        router.get(
            target.pathname + target.search,
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={id} className="sr-only">
                {t('common.sort.by')}
            </Label>
            <SelectField
                id={id}
                name="sort"
                className="min-w-56"
                defaultValue={encode(state.sort ?? '', state.direction)}
                onChange={change}
                options={choices.map((choice) => ({
                    value: encode(choice.sort, choice.direction),
                    label: choice.label,
                }))}
            />
        </div>
    );
}
