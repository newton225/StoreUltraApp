import { useEffect, useRef } from 'react';
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
};

/**
 * A native list. A filter has no visible label: its first option ("All statuses") then names it for screen
 * readers, while a list that has a `<label for>` keeps that label as its name.
 */
export default function SelectField({
    id,
    name,
    options,
    defaultValue,
    onChange,
    className,
    required,
}: Props) {
    const ref = useRef<HTMLSelectElement>(null);

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
            onChange={(event) => onChange?.(event.target.value)}
            className={cn(
                'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30',
                className,
            )}
        >
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}
