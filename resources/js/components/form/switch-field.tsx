import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props {
    id: string;
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
    error?: string;
    description?: string;
}

export function SwitchField({ id, label, checked, onChange, error, description }: Props) {
    return (
        <div className="space-y-2">
            <div className="flex items-center gap-3">
                <Switch id={id} checked={checked} onCheckedChange={onChange} aria-invalid={!!error} />
                <Label htmlFor={id}>{label}</Label>
            </div>
            {description && <p className="text-sm text-muted-foreground">{description}</p>}
            <FormError message={error} />
        </div>
    );
}
