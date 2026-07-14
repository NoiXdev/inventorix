import { Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';

export function ThemeToggle() {
    const { updateAppearance } = useAppearance();
    const options: [Appearance, string, typeof Sun][] = [
        ['light', 'Light', Sun],
        ['dark', 'Dark', Moon],
        ['system', 'System', Monitor],
    ];
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Toggle theme">
                    <Sun className="h-5 w-5 dark:hidden" />
                    <Moon className="hidden h-5 w-5 dark:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {options.map(([value, label, Icon]) => (
                    <DropdownMenuItem key={value} onClick={() => updateAppearance(value)}>
                        <Icon className="mr-2 h-4 w-4" /> {label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
