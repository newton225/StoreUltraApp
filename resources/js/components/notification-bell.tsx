import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Hint } from '@/components/hint';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAlertItems } from '@/hooks/use-alert-items';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The alerts in force, one click away from any screen: a bell with the number of them, and the list
 * with a link to where each is dealt with.
 */
export function NotificationBell() {
    const { t } = useTranslation();
    const { alerts } = usePage().props;
    const items = useAlertItems(alerts);

    if (alerts === null) {
        return null;
    }

    return (
        <DropdownMenu>
            <Hint label={t('dashboard.alerts.bell', { count: items.length })}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="relative"
                        aria-label={t('dashboard.alerts.bell', {
                            count: items.length,
                        })}
                        data-test="notification-bell"
                    >
                        <Bell />
                        {items.length > 0 && (
                            <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-semibold text-white">
                                {items.length}
                            </span>
                        )}
                    </Button>
                </DropdownMenuTrigger>
            </Hint>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel>
                    {t('dashboard.alerts.title')}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {items.length === 0 ? (
                    <p className="px-2 py-3 text-sm text-muted-foreground">
                        {t('dashboard.alerts.none')}
                    </p>
                ) : (
                    items.map((item) => (
                        <DropdownMenuItem
                            key={item.key}
                            asChild
                            className="flex-col items-start gap-1"
                        >
                            <Link href={item.href} prefetch>
                                <span>{item.text}</span>
                                <span className="text-xs text-muted-foreground underline underline-offset-4">
                                    {item.linkLabel}
                                </span>
                            </Link>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
