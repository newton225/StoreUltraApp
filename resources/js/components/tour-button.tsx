import { CircleHelp } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { Hint } from '@/components/hint';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { tourRegistry } from '@/lib/tour-registry';

/**
 * Replays the guided tour of the screen (playbook UI-34). It only shows on a screen that has a tour.
 */
export function TourButton() {
    const { t } = useTranslation();
    const start = useSyncExternalStore(
        tourRegistry.subscribe,
        tourRegistry.snapshot,
        () => null,
    );

    if (start === null) {
        return null;
    }

    return (
        <Hint label={t('tours.replay')}>
            <Button
                variant="ghost"
                size="icon"
                aria-label={t('tours.replay')}
                onClick={start}
                data-test="tour-replay"
            >
                <CircleHelp className="size-5" />
            </Button>
        </Hint>
    );
}
