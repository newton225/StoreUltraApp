import { ChevronDown, Download } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/ListExportController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';

type Query = Record<string, string | number | boolean | null | undefined>;

/**
 * Downloads the list of the page as CSV, Excel or PDF, with the filters that are on the screen (playbook
 * UI-116). The server only puts in the file what the user may see on the page.
 */
export default function ExportMenu({
    view,
    query = {},
}: {
    view: string;
    query?: Query;
}) {
    const { t } = useTranslation();
    const filters = Object.fromEntries(
        Object.entries(query).filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        ),
    ) as Record<string, string | number | boolean>;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <Download aria-hidden />
                    {t('exports.menu.label')}
                    <ChevronDown aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                    {t('exports.menu.help')}
                </DropdownMenuLabel>
                {(['xlsx', 'csv', 'pdf'] as const).map((format) => (
                    <DropdownMenuItem key={format} asChild>
                        <a
                            href={show.url(
                                { view, format },
                                { query: filters },
                            )}
                            download
                        >
                            {t(`exports.menu.${format}`)}
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
