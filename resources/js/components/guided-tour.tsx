import { router, usePage } from '@inertiajs/react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';
import { store } from '@/actions/App/Http/Controllers/TourController';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { tourRegistry } from '@/lib/tour-registry';

export type TourStep = {
    /** Value of the `data-tour` attribute of the element to show. */
    target: string;
    /** Translation key of the title, in the `tours` group. */
    title: string;
    /** Translation key of the text, in the `tours` group. */
    body: string;
};

type Rect = { top: number; left: number; width: number; height: number };

const PADDING = 6;
const CARD_WIDTH = 340;

const CARD_HEIGHT = 230;
const GAP = 12;

/**
 * Where the card goes so that it never covers what it explains: under the element if there is room, else
 * above, else to its right or left (a tall element such as the menu), else in the bottom corner.
 */
const cardPosition = (
    rect: Rect,
): { top?: number; left?: number; bottom?: number; right?: number } => {
    const width = window.innerWidth;
    const height = window.innerHeight;
    const clampLeft = (left: number): number =>
        Math.min(Math.max(GAP, left), Math.max(GAP, width - CARD_WIDTH - GAP));
    const clampTop = (top: number): number =>
        Math.min(Math.max(GAP, top), Math.max(GAP, height - CARD_HEIGHT - GAP));
    const bottomEdge = rect.top + rect.height;
    const rightEdge = rect.left + rect.width;

    if (height - bottomEdge - GAP >= CARD_HEIGHT) {
        return { top: bottomEdge + GAP, left: clampLeft(rect.left) };
    }

    if (rect.top - GAP >= CARD_HEIGHT) {
        return {
            top: rect.top - GAP - CARD_HEIGHT,
            left: clampLeft(rect.left),
        };
    }

    if (width - rightEdge - GAP >= CARD_WIDTH) {
        return { top: clampTop(rect.top), left: rightEdge + GAP };
    }

    if (rect.left - GAP >= CARD_WIDTH) {
        return { top: clampTop(rect.top), left: rect.left - GAP - CARD_WIDTH };
    }

    return { bottom: GAP, right: GAP };
};

const isVisible = (element: Element | null): element is HTMLElement => {
    if (!(element instanceof HTMLElement)) {
        return false;
    }

    const box = element.getBoundingClientRect();

    return box.width > 0 && box.height > 0;
};

const find = (target: string): HTMLElement | null => {
    const found = document.querySelector(`[data-tour="${target}"]`);

    return isVisible(found) ? found : null;
};

/**
 * The guided tour of a screen (playbook UI-34): it starts by itself the first time the member opens the
 * screen, can be replayed from the help button of the header, and what has been seen is kept in the database
 * by member. A step whose element is not on the screen (menu folded, button reserved to another profile, empty
 * list) is skipped. The text of a step says what the product does there, in a few words.
 */
export default function GuidedTour({
    tour,
    steps,
}: {
    tour: string;
    steps: TourStep[];
}) {
    const { t } = useTranslation();
    const { toursDone } = usePage().props;
    const [list, setList] = useState<TourStep[]>([]);
    const [index, setIndex] = useState(0);
    const [rect, setRect] = useState<Rect | null>(null);
    const [narrow, setNarrow] = useState(false);
    const nextButton = useRef<HTMLButtonElement>(null);
    const alreadySeen = toursDone.includes(tour);
    const active = list.length > 0;

    const start = useCallback((): void => {
        const present = steps.filter((step) => find(step.target) !== null);

        if (present.length === 0) {
            return;
        }

        setIndex(0);
        setList(present);
    }, [steps]);

    const close = useCallback((): void => {
        setList([]);

        if (!alreadySeen) {
            router.post(
                store.url(tour),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['toursDone'],
                },
            );
        }
    }, [alreadySeen, tour]);

    useEffect(() => tourRegistry.register(start), [start]);

    // First opening: wait for the page transition to finish before starting.
    useEffect(() => {
        if (alreadySeen) {
            return;
        }

        const timer = window.setTimeout(start, 700);

        return () => window.clearTimeout(timer);
        // Only the first opening of a screen starts the tour by itself.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tour]);

    const step: TourStep | undefined = list[index];

    const measure = useCallback((): void => {
        if (!step) {
            return;
        }

        const element = find(step.target);

        if (element === null) {
            setRect(null);

            return;
        }

        const box = element.getBoundingClientRect();
        setRect({
            top: box.top - PADDING,
            left: box.left - PADDING,
            width: box.width + PADDING * 2,
            height: box.height + PADDING * 2,
        });
        setNarrow(window.innerWidth < 640);
    }, [step]);

    useLayoutEffect(() => {
        if (!step) {
            return;
        }

        const element = find(step.target);
        const reduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        element?.scrollIntoView({
            block: 'center',
            behavior: reduced ? 'auto' : 'smooth',
        });
        measure();

        const settle = window.setTimeout(measure, 350);
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, true);

        return () => {
            window.clearTimeout(settle);
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', measure, true);
        };
    }, [step, measure]);

    useEffect(() => {
        if (!active) {
            return;
        }

        nextButton.current?.focus();

        const onKey = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                close();
            } else if (event.key === 'ArrowRight') {
                setIndex((value) => Math.min(value + 1, list.length - 1));
            } else if (event.key === 'ArrowLeft') {
                setIndex((value) => Math.max(value - 1, 0));
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [active, index, list.length, close]);

    if (!active || !step || rect === null) {
        return null;
    }

    const last = index === list.length - 1;
    const place = cardPosition(rect);

    return createPortal(
        <div className="fixed inset-0 z-[100]" data-test="guided-tour">
            <div
                className="absolute rounded-lg ring-2 ring-primary"
                style={{
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height,
                    boxShadow: '0 0 0 9999px rgba(0, 0, 0, 0.55)',
                }}
                aria-hidden="true"
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="tour-title"
                aria-describedby="tour-body"
                className={
                    narrow
                        ? 'fixed inset-x-3 bottom-3 space-y-3 rounded-xl border border-border bg-popover p-4 text-popover-foreground shadow-lg fade-in'
                        : 'fixed space-y-3 rounded-xl border border-border bg-popover p-4 text-popover-foreground shadow-lg fade-in'
                }
                style={narrow ? undefined : { ...place, width: CARD_WIDTH }}
            >
                <p className="text-xs text-muted-foreground" aria-live="polite">
                    {t('tours.progress', {
                        current: index + 1,
                        total: list.length,
                    })}
                </p>
                <h2 id="tour-title" className="text-base font-semibold">
                    {t(step.title)}
                </h2>
                <p id="tour-body" className="text-sm text-muted-foreground">
                    {t(step.body)}
                </p>
                <div className="flex items-center justify-between gap-2 pt-1">
                    <Button variant="ghost" size="sm" onClick={close}>
                        {t('tours.skip')}
                    </Button>
                    <div className="flex items-center gap-2">
                        {index > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setIndex(index - 1)}
                            >
                                {t('tours.previous')}
                            </Button>
                        )}
                        <Button
                            ref={nextButton}
                            size="sm"
                            onClick={() =>
                                last ? close() : setIndex(index + 1)
                            }
                        >
                            {last ? t('tours.finish') : t('tours.next')}
                        </Button>
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
