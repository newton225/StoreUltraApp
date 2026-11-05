import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useLeaveGuard } from '@/hooks/use-leave-guard';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    /** Whether something was changed since the form was shown or last saved (the `isDirty` of the form). */
    dirty: boolean;
    processing: boolean;
    /** A form that creates something: the button is always the main action, there is nothing saved yet. */
    isNew?: boolean;
    children: ReactNode;
    className?: string;
    'data-test'?: string;
};

/**
 * The save button of a form, always in view at the bottom of the screen however long the form is. It
 * says where things stand: with unsaved changes it takes the main colour with an amber mark and the
 * words "unsaved changes"; with none it calms down to an outline and says so. Leaving the page while
 * there are unsaved changes asks first.
 */
export function SaveBar({
    dirty,
    processing,
    isNew = false,
    children,
    className,
    'data-test': dataTest,
}: Props) {
    const { t } = useTranslation();
    const question = t('common.form.leave');

    useLeaveGuard(dirty && !processing, question);

    return (
        <div
            className={cn(
                'sticky bottom-0 z-20 order-last col-span-full mt-6 flex items-center gap-4 border-t bg-background/95 py-3 backdrop-blur',
                className,
            )}
        >
            <Button
                disabled={processing}
                variant={dirty || isNew ? 'success' : 'outline'}
                className={cn(
                    'transition-[background-color,color,box-shadow]',
                    dirty &&
                        'ring-2 ring-success/50 ring-offset-2 ring-offset-background',
                )}
                data-test={dataTest}
            >
                {processing ? t('common.form.saving') : children}
            </Button>
            <p
                role="status"
                className={cn(
                    'flex items-center gap-2 text-sm transition-colors',
                    dirty ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {dirty && (
                    <span
                        aria-hidden
                        className="size-2 rounded-full bg-amber-500"
                    />
                )}
                {dirty
                    ? t('common.form.unsaved')
                    : isNew
                      ? ''
                      : t('common.form.saved')}
            </p>
        </div>
    );
}
