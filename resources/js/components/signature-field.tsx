import SignaturePad from 'signature_pad';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    name: string;
    /** The id the label points to, so that the label shows the asterisk of a required signature. */
    id?: string;
    required?: boolean;
};

/**
 * A box to sign with a finger or a pen. The drawing travels with the form as a PNG data URL.
 */
export default function SignatureField({ name, id, required }: Props) {
    const { t } = useTranslation();
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const padRef = useRef<SignaturePad | null>(null);

    useEffect(() => {
        const canvas = canvasRef.current;

        if (!canvas) {
            return;
        }

        const resize = (): void => {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);

            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d')?.scale(ratio, ratio);
            padRef.current?.clear();

            if (inputRef.current) {
                inputRef.current.value = '';
            }
        };

        const pad = new SignaturePad(canvas, {
            penColor: '#111827',
            backgroundColor: 'rgb(255, 255, 255)',
        });

        padRef.current = pad;
        resize();

        pad.addEventListener('endStroke', () => {
            if (inputRef.current) {
                inputRef.current.value = pad.isEmpty()
                    ? ''
                    : pad.toDataURL('image/png');
            }
        });

        window.addEventListener('resize', resize);

        return () => {
            window.removeEventListener('resize', resize);
            pad.off();
        };
    }, []);

    const clear = (): void => {
        padRef.current?.clear();

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    return (
        <div className="space-y-2">
            <canvas
                ref={canvasRef}
                className="h-40 w-full touch-none rounded-md border border-input bg-white"
                aria-label={t('deliveries.panel.signature')}
            />
            <input
                ref={inputRef}
                type="hidden"
                id={id}
                name={name}
                required={required}
            />
            <Button type="button" variant="outline" size="sm" onClick={clear}>
                {t('deliveries.panel.clear')}
            </Button>
        </div>
    );
}
