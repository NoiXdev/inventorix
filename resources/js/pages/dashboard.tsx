import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function Dashboard() {
    return (
        <AppLayout title="Dashboard" breadcrumbs={[{ label: 'Dashboard' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Dashboard</h1>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {['Assets', 'Handovers', 'Open incidents', 'Users'].map((label) => (
                    <Card key={label}>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{label}</CardTitle></CardHeader>
                        <CardContent className="text-2xl font-semibold">—</CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
