import { useState } from 'react';
import { ScanLine } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/theme-toggle';
import { UserMenu } from '@/components/user-menu';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ScanDialog } from '@/components/scan/scan-dialog';
import type { BreadcrumbItem } from '@/types';

export function AppTopbar({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    const [scanOpen, setScanOpen] = useState(false);

    return (
        <header className="flex h-14 items-center gap-4 border-b bg-card px-4">
            <div className="flex-1"><Breadcrumbs items={breadcrumbs} /></div>
            <Input placeholder="Search…" className="hidden w-64 lg:block" disabled />
            <Button variant="ghost" size="icon" onClick={() => setScanOpen(true)} aria-label="Scan QR">
                <ScanLine className="h-5 w-5" />
            </Button>
            <ScanDialog open={scanOpen} onClose={() => setScanOpen(false)} />
            <ThemeToggle />
            <UserMenu />
        </header>
    );
}
