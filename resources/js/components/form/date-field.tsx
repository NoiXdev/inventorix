import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props { id: string; label: string; value: string; onChange: (v: string) => void; error?: string; required?: boolean; }

export function DateField({ id, label, value, onChange, error, required }: Props) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <Input id={id} type="date" value={value} aria-invalid={!!error} onChange={(e) => onChange(e.target.value)} />
            <FormError message={error} />
        </div>
    );
}
