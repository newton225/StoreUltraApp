import SelectField from '@/components/select-field';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The choice of a week of delivery, from the weeks that have orders (playbook UI-132): « Semaine du 2 nov. 2026 »
 * is found without typing a date, and the first day of the week is always the one the business uses.
 * The list can be searched (typing "nov" or "2026" narrows it), so a long history stays easy to use. `allowAll` adds an empty choice for a filter that may also show every week.
 */
export default function WeekSelect({
    id,
    weeks,
    value,
    onChange,
    allowAll = false,
    name = 'week',
}: {
    id: string;
    weeks: string[];
    value: string;
    onChange: (week: string) => void;
    allowAll?: boolean;
    name?: string;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();

    return (
        <SelectField
            key={value}
            id={id}
            name={name}
            defaultValue={value}
            searchable
            onChange={onChange}
            options={[
                ...(allowAll
                    ? [{ value: '', label: t('common.all_weeks') }]
                    : []),
                ...weeks.map((week) => ({
                    value: week,
                    label: t('common.week_of', { date: date(week) }),
                })),
            ]}
        />
    );
}
