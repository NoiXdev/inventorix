import { Link } from '@inertiajs/react';
import { AlertTriangle, Pencil } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Can } from '@/components/can';

interface ComponentRecord {
    id: string; name: string; model_number: string | null; serial: string | null; qty: number; min_amt: number | null;
    purchase_cost: string | null; purchase_date: string | null; order_number: string | null;
    notes: string | null; image_url: string | null; category_name: string | null; manufacturer_name: string | null;
    location_name: string | null; supplier_name: string | null; checked_out: number; remaining: number; low_stock: boolean;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{children ?? '—'}</div>
        </div>
    );
}

export default function ShowComponent({ component }: { component: ComponentRecord }) {
    return (
        <AppLayout title={component.name} breadcrumbs={[{ label: 'Components', href: '/app/components' }, { label: component.name }]}>
            <div className="mb-6 flex items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{component.name}</h1>
                    {component.low_stock && <Badge variant="destructive"><AlertTriangle className="mr-1 h-3 w-3" /> Low stock</Badge>}
                </div>
                <Can permission="components.edit">
                    <Button asChild variant="outline"><Link href={`/app/components/${component.id}/edit`}><Pencil className="h-4 w-4" /> Edit</Link></Button>
                </Can>
            </div>

            <Card>
                <CardHeader className="pb-2"><CardTitle className="text-base">Details</CardTitle></CardHeader>
                <CardContent className="grid grid-cols-2 gap-6 md:grid-cols-3">
                    {component.image_url && <img src={component.image_url} alt="" className="col-span-2 h-24 w-24 rounded border object-contain md:col-span-3" />}
                    <Field label="Category">{component.category_name}</Field>
                    <Field label="Manufacturer">{component.manufacturer_name}</Field>
                    <Field label="Model number">{component.model_number}</Field>
                    <Field label="Serial number">{component.serial}</Field>
                    <Field label="Location">{component.location_name}</Field>
                    <Field label="Available / quantity">{component.remaining} / {component.qty}</Field>
                    <Field label="Minimum quantity">{component.min_amt}</Field>
                    <Field label="Supplier">{component.supplier_name}</Field>
                    <Field label="Purchase date">{component.purchase_date}</Field>
                    <Field label="Purchase cost">{component.purchase_cost}</Field>
                    <Field label="Order number">{component.order_number}</Field>
                    {component.notes && <div className="col-span-2 md:col-span-3"><Field label="Notes"><span className="whitespace-pre-line">{component.notes}</span></Field></div>}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
