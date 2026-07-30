import { useMemo, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Option { value: string; label: string }
interface Props { id: string; label: string; options: Option[]; value: string[]; onChange: (v: string[]) => void }

export function MultiSelectField({ id, label, options, value, onChange }: Props) {
    const [q, setQ] = useState('');
    const filtered = useMemo(
        () => options.filter((o) => o.label.toLowerCase().includes(q.toLowerCase())),
        [options, q],
    );

    const toggle = (v: string) =>
        onChange(value.includes(v) ? value.filter((x) => x !== v) : [...value, v]);

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Suchen…" className="mb-1" />
            <div className="max-h-40 overflow-y-auto rounded-md border p-2">
                {filtered.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Keine Optionen.</p>
                ) : (
                    filtered.map((o) => (
                        <label key={o.value} className="flex cursor-pointer items-center gap-2 py-1 text-sm">
                            <Checkbox checked={value.includes(o.value)} onCheckedChange={() => toggle(o.value)} />
                            {o.label}
                        </label>
                    ))
                )}
            </div>
        </div>
    );
}
