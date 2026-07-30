import { useState } from 'react';
import { X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props { id: string; label: string; value: string[]; onChange: (v: string[]) => void; error?: string; }

export function TagsInput({ id, label, value, onChange, error }: Props) {
    const [draft, setDraft] = useState('');

    const addTag = () => {
        const tag = draft.trim();
        if (tag === '' || value.includes(tag)) { setDraft(''); return; }
        onChange([...value, tag]);
        setDraft('');
    };

    const removeTag = (tag: string) => onChange(value.filter((t) => t !== tag));

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex flex-wrap gap-1.5">
                {value.map((tag) => (
                    <Badge key={tag} variant="secondary" className="gap-1">
                        {tag}
                        <button type="button" aria-label={`Remove ${tag}`} onClick={() => removeTag(tag)} className="rounded-sm hover:text-destructive">
                            <X className="h-3 w-3" />
                        </button>
                    </Badge>
                ))}
            </div>
            <Input id={id} value={draft} aria-invalid={!!error}
                placeholder="Add a tag and press Enter"
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(); }
                }} />
            <FormError message={error} />
        </div>
    );
}
