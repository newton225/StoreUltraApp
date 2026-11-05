import { useEffect, useRef, useState } from 'react';
import SearchableSelect from '@/components/searchable-select';
import { cn } from '@/lib/utils';

export type SelectOption = {
    value: string;
    label: string;
};

type Props = {
    id: string;
    name: string;
    options: SelectOption[];
    defaultValue?: string;
    onChange?: (value: string) => void;
    className?: string;
    required?: boolean;
    /** The empty option is an invitation to choose (default: when the list is required). */
    prompt?: boolean;
    /** Searchable list; by default a list of eight choices or more is (see SEARCH_FROM). */
    searchable?: boolean;
};

/** From this many options, a list can be searched instead of scrolled. */
const SEARCH_FROM = 8;

/**
 * A native list. A filter has no visible label: its first option ("All statuses") then names it for screen
 * readers, while a list that has a `<label for>` keeps that label as its name.
 *
 * In a required list, the empty option is an invitation ("Choose a kit"), not a value: it is shown between
 * dashes, in grey italics, and the closed list stays grey until a real value is picked, so it can never be
 * mistaken for data (playbook UI-120).
 */
function NativeSelect({
    id,
    name,
    options,
    defaultValue,
    onChange,
    className,
    required,
    prompt,
}: Omit<Props, 'searchable'>) {
    const ref = useRef<HTMLSelectElement>(null);
    const [value, setValue] = useState(defaultValue ?? options[0]?.value ?? '');
    const asksChoice = prompt ?? required === true;
    const prompting = asksChoice && value === '';

    useEffect(() => {
        const select = ref.current;

        if (
            select &&
            !select.labels?.length &&
            !select.hasAttribute('aria-label')
        ) {
            select.setAttribute('aria-label', options[0]?.label ?? name);
        }
    }, [options, name]);

    return (
        <select
            ref={ref}
            id={id}
            name={name}
            required={required}
            defaultValue={defaultValue}
            onChange={(event) => {
                setValue(event.target.value);
                onChange?.(event.target.value);
            }}
            className={cn(
                'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30',
                prompting && 'text-muted-foreground italic',
                className,
            )}
        >
            {options.map((option) =>
                asksChoice && option.value === '' ? (
                    <option
                        key={option.value}
                        value={option.value}
                        className="bg-popover text-muted-foreground italic"
                    >
                        {`— ${option.label} —`}
                    </option>
                ) : (
                    <option
                        key={option.value}
                        value={option.value}
                        className="bg-popover text-popover-foreground not-italic"
                    >
                        {option.label}
                    </option>
                ),
            )}
        </select>
    );
}

/**
 * A list of choices (playbook UI-120, UI-121): native when it is short, searchable when it can grow long, with
 * the same props and the same form behaviour in both cases.
 */
export default function SelectField(props: Props) {
    const { searchable, ...rest } = props;
    const searchableList = searchable ?? props.options.length >= SEARCH_FROM;

    return searchableList ? (
        <SearchableSelect
            {...rest}
            prompt={props.prompt ?? props.required === true}
        />
    ) : (
        <NativeSelect {...rest} />
    );
}
