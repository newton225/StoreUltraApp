import type { CSSProperties } from 'react';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useCurrentAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

type StyleWithVariables = CSSProperties & { [name: `--${string}`]: string };

const toasterStyle: StyleWithVariables = {
    '--normal-bg': 'var(--popover)',
    '--normal-text': 'var(--popover-foreground)',
    '--normal-border': 'var(--border)',
};

function Toaster({ ...props }: ToasterProps) {
    const appearance = useCurrentAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={toasterStyle}
            {...props}
        />
    );
}

export { Toaster };
