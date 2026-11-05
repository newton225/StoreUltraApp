import { CheckIcon, ChevronDownIcon, SearchIcon } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type SearchableOption = {
    value: string;
    label: string;
};

type Props = {
    id: string;
    name: string;
    options: SearchableOption[];
    defaultValue?: string;
    onChange?: (value: string) => void;
    className?: string;
    required?: boolean;
    /** The empty option is an invitation to choose, shown as such (playbook UI-120). */
    prompt: boolean;
};

/** Lower case without accents, so that "ete" finds "Été" and "Niakara" finds "niakara". */
const normalise = (text: string): string =>
    text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

/**
 * A list of choices that can be searched, for the lists that grow long (clients, products, shops...).
 * A real `<select>` stays underneath, invisible: it holds the value, takes part in the form, gives the native
 * validation of a required field and keeps the label's `for` working, so nothing around it changes.
 * The panel opens under the field (above it when there is no room), filters as you type without caring for
 * accents, and works with the keyboard: arrows to move, Enter to choose, Escape to close (playbook UI-121).
 */
export default function SearchableSelect({
    id,
    name,
    options,
    defaultValue,
    onChange,
    className,
    required,
    prompt,
}: Props) {
    const { t } = useTranslation();
    const listId = useId();
    const root = useRef<HTMLDivElement>(null);
    const select = useRef<HTMLSelectElement>(null);
    const trigger = useRef<HTMLButtonElement>(null);
    const search = useRef<HTMLInputElement>(null);
    const list = useRef<HTMLUListElement>(null);

    const [value, setValue] = useState(defaultValue ?? options[0]?.value ?? '');
    const [open, setOpen] = useState(false);
    const [upwards, setUpwards] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);

    const visible = useMemo(() => {
        const wanted = normalise(query.trim());

        return wanted === ''
            ? options
            : options.filter(
                  (option) =>
                      option.value !== '' &&
                      normalise(option.label).includes(wanted),
              );
    }, [options, query]);

    const current = options.find((option) => option.value === value);
    const showingPrompt = prompt && value === '';

    const close = (refocus: boolean): void => {
        setOpen(false);
        setQuery('');

        if (refocus) {
            trigger.current?.focus();
        }
    };

    const openList = (): void => {
        const box = root.current?.getBoundingClientRect();

        if (box) {
            const below = window.innerHeight - box.bottom;

            setUpwards(below < 300 && box.top > below);
        }

        const selected = options.findIndex((option) => option.value === value);

        setActive(Math.max(0, selected));
        setOpen(true);
    };

    // What the user picks goes through the real select, so the form, the validation and `onChange` all see it.
    const choose = (next: string): void => {
        const element = select.current;

        if (element && element.value !== next) {
            Object.getOwnPropertyDescriptor(
                HTMLSelectElement.prototype,
                'value',
            )?.set?.call(element, next);
            element.dispatchEvent(new Event('change', { bubbles: true }));
        }

        close(true);
    };

    useEffect(() => {
        if (open) {
            search.current?.focus();
        }
    }, [open]);

    // A click anywhere else closes the panel.
    useEffect(() => {
        if (!open) {
            return;
        }

        const away = (event: PointerEvent): void => {
            if (!root.current?.contains(event.target as Node)) {
                setOpen(false);
                setQuery('');
            }
        };

        document.addEventListener('pointerdown', away);

        return () => document.removeEventListener('pointerdown', away);
    }, [open]);

    // The highlighted line always stays in view.
    useEffect(() => {
        if (open) {
            list.current
                ?.querySelector<HTMLElement>('[data-active="true"]')
                ?.scrollIntoView({ block: 'nearest' });
        }
    }, [open, active, visible]);

    // A reset of the form puts the value back.
    useEffect(() => {
        const element = select.current;
        const form = element?.form;

        if (!element || !form) {
            return;
        }

        const reset = (): void => {
            window.setTimeout(() => setValue(element.value), 0);
        };

        form.addEventListener('reset', reset);

        return () => form.removeEventListener('reset', reset);
    }, []);

    // The button is named by the label of the field, or by its first option for a filter without a label.
    useEffect(() => {
        const element = select.current;
        const button = trigger.current;

        if (!element || !button) {
            return;
        }

        const label = element.labels?.[0]?.textContent?.trim();

        button.setAttribute('aria-label', label || options[0]?.label || name);
    }, [options, name]);

    const onSearchKey = (event: KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(index + 1, visible.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Home') {
            event.preventDefault();
            setActive(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            setActive(Math.max(visible.length - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();

            if (visible[active]) {
                choose(visible[active].value);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close(true);
        } else if (event.key === 'Tab') {
            close(false);
        }
    };

    return (
        <div ref={root} className="relative">
            <select
                ref={select}
                id={id}
                name={name}
                required={required}
                defaultValue={defaultValue}
                tabIndex={-1}
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 h-full w-full opacity-0"
                onFocus={() => trigger.current?.focus()}
                onChange={(event) => {
                    setValue(event.target.value);
                    onChange?.(event.target.value);
                }}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>

            <button
                ref={trigger}
                type="button"
                role="combobox"
                aria-expanded={open}
                aria-haspopup="listbox"
                aria-controls={open ? listId : undefined}
                onClick={() => (open ? close(false) : openList())}
                onKeyDown={(event) => {
                    if (
                        !open &&
                        ['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(
                            event.key,
                        )
                    ) {
                        event.preventDefault();
                        openList();
                    }
                }}
                className={cn(
                    'flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1 text-left text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30',
                    showingPrompt && 'text-muted-foreground italic',
                    className,
                )}
            >
                <span className="truncate">
                    {showingPrompt && current
                        ? `— ${current.label} —`
                        : (current?.label ?? '')}
                </span>
                <ChevronDownIcon
                    className={cn(
                        'size-4 shrink-0 opacity-50 transition-transform duration-150 ease-out',
                        open && 'rotate-180',
                    )}
                />
            </button>

            {open && (
                <div
                    className={cn(
                        'absolute z-50 w-full min-w-48 overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-md fade-in',
                        upwards ? 'bottom-full mb-1' : 'top-full mt-1',
                    )}
                >
                    <div className="flex items-center gap-2 border-b border-border px-3">
                        <SearchIcon className="size-4 shrink-0 opacity-50" />
                        <input
                            ref={search}
                            type="text"
                            role="searchbox"
                            value={query}
                            autoComplete="off"
                            placeholder={t('common.select.search')}
                            aria-label={t('common.select.search_label')}
                            aria-controls={listId}
                            aria-activedescendant={
                                visible[active]
                                    ? `${listId}-${active}`
                                    : undefined
                            }
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setActive(0);
                            }}
                            onKeyDown={onSearchKey}
                            className="h-9 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                    </div>

                    <ul
                        ref={list}
                        id={listId}
                        role="listbox"
                        className="max-h-60 overflow-y-auto p-1"
                    >
                        {visible.length === 0 ? (
                            <li className="px-2 py-3 text-center text-sm text-muted-foreground">
                                {t('common.select.no_results')}
                            </li>
                        ) : (
                            visible.map((option, index) => {
                                const isPrompt = prompt && option.value === '';

                                return (
                                    <li
                                        key={option.value}
                                        id={`${listId}-${index}`}
                                        role="option"
                                        aria-selected={option.value === value}
                                        data-active={index === active}
                                        onPointerEnter={() => setActive(index)}
                                        onClick={() => choose(option.value)}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors duration-100',
                                            index === active &&
                                                'bg-accent text-accent-foreground',
                                            isPrompt &&
                                                'text-muted-foreground italic',
                                        )}
                                    >
                                        <span className="flex-1 truncate">
                                            {isPrompt
                                                ? `— ${option.label} —`
                                                : option.label}
                                        </span>
                                        {option.value === value && (
                                            <CheckIcon className="size-4 shrink-0" />
                                        )}
                                    </li>
                                );
                            })
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
