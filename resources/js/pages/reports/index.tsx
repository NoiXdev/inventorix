import { Link } from '@inertiajs/react';
import { Banknote, ClipboardCheck, Clock, MapPin, PieChart, ShieldCheck, Users, Wrench, type LucideIcon } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const ICONS: Record<string, LucideIcon> = {
    Users, ShieldCheck, MapPin, Banknote, PieChart, Wrench, Clock, ClipboardCheck,
};

interface Report { key: string; label: string; description: string; icon: string }

export default function ReportsIndex({ reports }: { reports: Report[] }) {
    return (
        <AppLayout title="Reports" breadcrumbs={[{ label: 'Reports' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Reports</h1>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {reports.map((r) => {
                    const Icon = ICONS[r.icon] ?? PieChart;
                    return (
                        <Link key={r.key} href={`/app/reports/${r.key}`} className="block">
                            <Card className="h-full transition-colors hover:border-primary">
                                <CardHeader className="flex flex-row items-center gap-3 pb-2">
                                    <Icon className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle className="text-base">{r.label}</CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm text-muted-foreground">{r.description}</CardContent>
                            </Card>
                        </Link>
                    );
                })}
            </div>
        </AppLayout>
    );
}
