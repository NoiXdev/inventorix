import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { NumberField } from '@/components/form/number-field';
import { QrPrintModal } from '@/components/qr-print/qr-print-modal';
import type { LabelItem } from '@/plugins/qr-print/types';

export default function QrGenerator() {
    const [amount, setAmount] = useState('20');
    const [items, setItems] = useState<LabelItem[] | null>(null);
    const [loading, setLoading] = useState(false);

    const n = Math.max(1, Math.min(1000, parseInt(amount || '0', 10) || 0));

    const printLabels = async () => {
        setLoading(true);
        try {
            const res = await fetch(`/app/qr-generator/codes?amount=${n}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            const data = (await res.json()) as { uuids: string[] };
            setItems(data.uuids.map((u) => ({ uuid: u })));
        } finally { setLoading(false); }
    };

    return (
        <AppLayout title="QR Generator" breadcrumbs={[{ label: 'QR Generator' }]}>
            <h1 className="mb-6 text-2xl font-semibold">QR Generator</h1>
            <div className="max-w-md space-y-4">
                <NumberField id="amount" label="Anzahl" step="1" min="1" value={amount} onChange={setAmount} />
                <div className="flex gap-2">
                    <Button asChild variant="outline"><a href={`/app/qr-generator/download?amount=${n}`}>TXT herunterladen</a></Button>
                    <Button type="button" onClick={printLabels} disabled={loading}>Etiketten drucken</Button>
                </div>
            </div>
            <QrPrintModal open={!!items} items={items ?? []} onClose={() => setItems(null)} />
        </AppLayout>
    );
}
