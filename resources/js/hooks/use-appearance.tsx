import { router, usePage } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';
import { update as saveAppearance } from '@/actions/App/Http/Controllers/Settings/AppearanceController';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

export function isAppearance(value: string | null): value is Appearance {
    return value === 'light' || value === 'dark' || value === 'system';
}

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return 'system';
    }

    const stored = localStorage.getItem('appearance');

    return isAppearance(stored) ? stored : 'system';
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

/**
 * Where the last click or key press happened: the new theme spreads from there.
 */
const origin = { x: 0, y: 0, known: false };

if (typeof window !== 'undefined') {
    window.addEventListener(
        'pointerdown',
        (event) => {
            origin.x = event.clientX;
            origin.y = event.clientY;
            origin.known = true;
        },
        { capture: true, passive: true },
    );

    window.addEventListener(
        'keydown',
        () => {
            const focused = document.activeElement?.getBoundingClientRect();

            if (focused) {
                origin.x = focused.left + focused.width / 2;
                origin.y = focused.top + focused.height / 2;
                origin.known = true;
            }
        },
        { capture: true, passive: true },
    );
}

/**
 * Change the theme as a circle that spreads from the point of the click and uncovers the new look
 * over the old one. Where the browser cannot do it, or the reader asked for less motion, the theme
 * simply changes.
 */
const applyThemeSpreading = (mode: Appearance): void => {
    const root = document.documentElement;
    const changes = isDarkMode(mode) !== root.classList.contains('dark');
    const reduced = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    if (!changes || reduced || !('startViewTransition' in document)) {
        applyTheme(mode);

        return;
    }

    const x = origin.known ? origin.x : window.innerWidth / 2;
    const y = origin.known ? origin.y : window.innerHeight / 2;
    const radius = Math.hypot(
        Math.max(x, window.innerWidth - x),
        Math.max(y, window.innerHeight - y),
    );

    const transition = document.startViewTransition(() => applyTheme(mode));

    void transition.ready.then(() => {
        root.animate(
            {
                clipPath: [
                    `circle(0px at ${x}px ${y}px)`,
                    `circle(${radius}px at ${x}px ${y}px)`,
                ],
            },
            {
                duration: 600,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                pseudoElement: '::view-transition-new(root)',
            },
        );
    });
};

/**
 * Apply a mode on this device only (no request to the server). A change made by the user spreads
 * from where they clicked; one that only follows the profile on load does not.
 */
const applyLocally = (mode: Appearance, spread = false): void => {
    currentAppearance = mode;

    // Store in localStorage for client-side persistence...
    localStorage.setItem('appearance', mode);

    // Store in cookie for SSR...
    setCookie('appearance', mode);

    if (spread) {
        applyThemeSpreading(mode);
    } else {
        applyTheme(mode);
    }

    notify();
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (!localStorage.getItem('appearance')) {
        localStorage.setItem('appearance', 'system');
        setCookie('appearance', 'system');
    }

    currentAppearance = getStoredAppearance();
    applyTheme(currentAppearance);

    // Set up system theme change listener
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

/**
 * Align this device with the mode saved in the profile of the logged-in user.
 */
export function useAppearanceSync(): void {
    const { auth } = usePage().props;
    const saved = auth.user?.appearance;

    useEffect(() => {
        if (saved && saved !== currentAppearance) {
            applyLocally(saved);
        }
    }, [saved]);
}

/**
 * The mode in use on this device. It does not need the Inertia page, so it
 * can be used outside of it (the toaster is mounted around the app).
 */
export function useCurrentAppearance(): Appearance {
    return useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );
}

export function useAppearance(): UseAppearanceReturn {
    const { auth } = usePage().props;

    const appearance = useCurrentAppearance();

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance): void => {
        applyLocally(mode, true);

        if (auth.user) {
            router.patch(
                saveAppearance.url(),
                { appearance: mode },
                { preserveScroll: true, preserveState: true },
            );
        }
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
