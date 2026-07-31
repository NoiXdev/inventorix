import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Option { value: string; label: string }

interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    options: Option[];
    error?: string;
    required?: boolean;
    placeholder?: string;
    nullable?: boolean;
    footer?: (query: string) => React.ReactNode;
}

export function ComboboxField({
    id, label, value, onChange, options, error, required, placeholder, nullable, footer,
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    const selected = options.find((o) => o.value === value);
    const filtered = useMemo(
        () => options.filter((o) => o.label.toLowerCase().includes(query.toLowerCase())),
        [options, query],
    );

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    const close = () => { setOpen(false); setQuery(''); };
    const pick = (v: string) => { onChange(v); close(); };

    return (
        <div className="space-y-2" ref={containerRef}>
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <div className="relative">
                <Button
                    type="button"
                    id={id}
                    variant="outline"
                    aria-invalid={!!error}
                    aria-label={placeholder ?? `Select ${label.toLowerCase()}…`}
                    className="w-full justify-between font-normal"
                    onClick={() => setOpen((o) => !o)}
                >
                    <span className={selected ? '' : 'text-muted-foreground'}>
                        {selected ? selected.label : (placeholder ?? `Select ${label.toLowerCase()}…`)}
                    </span>
                </Button>
                {open && (
                    <div className="absolute z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                        <Input
                            autoFocus
                            value={query}
                            placeholder="Suchen…"
                            className="mb-1"
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Escape') close(); }}
                        />
                        <div className="max-h-48 overflow-y-auto">
                            {nullable && (
                                <button type="button" className="block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                    onClick={() => pick('')}>—</button>
                            )}
                            {filtered.length === 0 ? (
                                <p className="px-2 py-1.5 text-sm text-muted-foreground">Keine Treffer.</p>
                            ) : (
                                filtered.map((o) => (
                                    <button key={o.value} type="button"
                                        className="block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        onClick={() => pick(o.value)}>
                                        {o.label}
                                    </button>
                                ))
                            )}
                        </div>
                        {footer && <div className="mt-1 border-t pt-1">{footer(query)}</div>}
                    </div>
                )}
            </div>
            <FormError message={error} />
        </div>
    );
}
