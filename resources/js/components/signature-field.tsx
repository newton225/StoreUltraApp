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

        let width = 0;

        // The drawing is kept when the box changes: only a new width redraws it (scaled), a change of height alone
        // (the keyboard of a phone opening) leaves it untouched.
        const resize = (): void => {
            if (canvas.offsetWidth === width && width !== 0) {
                return;
            }

            const strokes = padRef.current?.toData() ?? [];
            const scale = width === 0 ? 1 : canvas.offsetWidth / width;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);

            width = canvas.offsetWidth;
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d')?.scale(ratio, ratio);
            padRef.current?.clear();

            if (strokes.length > 0) {
                padRef.current?.fromData(
                    strokes.map((stroke) => ({
                        ...stroke,
                        points: stroke.points.map((point) => ({
                            ...point,
                            x: point.x * scale,
                        })),
                    })),
                );
            }

            if (inputRef.current) {
                inputRef.current.value =
                    padRef.current === null || padRef.current.isEmpty()
                        ? ''
                        : padRef.current.toDataURL('image/png');
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
