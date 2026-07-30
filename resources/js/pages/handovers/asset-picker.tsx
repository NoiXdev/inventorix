import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

export interface AssetOption { value: string; label: string; state: string; }

interface Props { options: AssetOption[]; selected: string[]; onChange: (ids: string[]) => void; }

export function AssetPicker({ options, selected, onChange }: Props) {
    const [q, setQ] = useState('');
    const shown = options.filter((o) => o.label.toLowerCase().includes(q.toLowerCase()));

    const toggle = (id: string) =>
        onChange(selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id]);

    return (
        <div className="space-y-2">
            <Input placeholder="Filter assets…" value={q} onChange={(e) => setQ(e.target.value)} />
            <div className="max-h-64 space-y-1 overflow-y-auto rounded-md border p-2">
                {shown.length === 0 && <p className="p-2 text-sm text-muted-foreground">No eligible assets.</p>}
                {shown.map((o) => (
                    <label key={o.value} htmlFor={`asset-${o.value}`} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-accent">
                        <Checkbox id={`asset-${o.value}`} checked={selected.includes(o.value)} onCheckedChange={() => toggle(o.value)} />
                        <span>{o.label}</span>
                    </label>
                ))}
            </div>
        </div>
    );
}
