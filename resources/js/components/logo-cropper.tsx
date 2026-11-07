import { useRef, useState } from 'react';
import type { PointerEvent } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Box = { x: number; y: number; w: number; h: number };

type Props = {
    src: string;
    name: string;
    onCropped: (file: File) => void;
    onCancel: () => void;
};

const MIN = 8;

/**
 * Crop window: drag on the picture to draw the part to keep, then apply.
 * Without a selection the whole image is kept. Made with a canvas, no library.
 */
export function LogoCropper({ src, name, onCropped, onCancel }: Props) {
    const { t } = useTranslation();
    const image = useRef<HTMLImageElement>(null);
    const start = useRef<{ x: number; y: number } | null>(null);
    const [box, setBox] = useState<Box | null>(null);

    const point = (event: PointerEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        return {
            x: Math.min(Math.max(event.clientX - rect.left, 0), rect.width),
            y: Math.min(Math.max(event.clientY - rect.top, 0), rect.height),
        };
    };

    const down = (event: PointerEvent<HTMLDivElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        start.current = point(event);
        setBox(null);
    };

    const move = (event: PointerEvent<HTMLDivElement>) => {
        if (start.current === null) {
            return;
        }

        const now = point(event);
        setBox({
            x: Math.min(start.current.x, now.x),
            y: Math.min(start.current.y, now.y),
            w: Math.abs(now.x - start.current.x),
            h: Math.abs(now.y - start.current.y),
        });
    };

    const up = () => {
        start.current = null;
        setBox((current) =>
            current !== null && current.w >= MIN && current.h >= MIN
                ? current
                : null,
        );
    };

    const apply = () => {
        const img = image.current;

        if (img === null) {
            return;
        }

        const scale = img.naturalWidth / img.clientWidth;
        const area = box ?? {
            x: 0,
            y: 0,
            w: img.clientWidth,
            h: img.clientHeight,
        };
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(area.w * scale));
        canvas.height = Math.max(1, Math.round(area.h * scale));
        canvas
            .getContext('2d')
            ?.drawImage(
                img,
                area.x * scale,
                area.y * scale,
                area.w * scale,
                area.h * scale,
                0,
                0,
                canvas.width,
                canvas.height,
            );
        canvas.toBlob((blob) => {
            if (blob !== null) {
                onCropped(
                    new File([blob], `${name.replace(/\.[^.]+$/, '')}.png`, {
                        type: 'image/png',
                    }),
                );
            }
        }, 'image/png');
    };

    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                {t('company.logo.crop_help')}
            </p>
            <div
                className="bg-checkerboard relative w-fit max-w-full cursor-crosshair touch-none overflow-hidden rounded-md border border-border select-none"
                onPointerDown={down}
                onPointerMove={move}
                onPointerUp={up}
            >
                <img
                    ref={image}
                    src={src}
                    alt={t('company.logo.preview_alt')}
                    draggable={false}
                    className="block max-h-80 max-w-full"
                />
                {box !== null && (
                    <div
                        className="pointer-events-none absolute border-2 border-dashed border-white shadow-[0_0_0_1px_rgba(0,0,0,0.7),inset_0_0_0_1px_rgba(0,0,0,0.7)]"
                        style={{
                            left: box.x,
                            top: box.y,
                            width: box.w,
                            height: box.h,
                            boxShadow: '0 0 0 9999px rgb(0 0 0 / 0.55)',
                        }}
                    />
                )}
            </div>
            <div className="flex flex-wrap gap-3">
                <Button type="button" variant="success" onClick={apply}>
                    {box === null
                        ? t('company.logo.crop_keep')
                        : t('company.logo.crop_apply')}
                </Button>
                <Button type="button" variant="outline" onClick={onCancel}>
                    {t('company.logo.crop_cancel')}
                </Button>
            </div>
        </div>
    );
}
