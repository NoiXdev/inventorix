import { useEffect, useId, useRef, useState } from 'react';
import { Trash2, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props {
    id: string;
    label: string;
    /** The newly picked file, or null when nothing is staged. */
    value: File | null;
    onChange: (file: File | null) => void;
    error?: string;
    /** `accept` attribute, e.g. "image/png,image/jpeg". */
    accept?: string;
    /** Shown under the control; pair it with the server-side rule. */
    hint?: string;
    /** URL of the file already stored on the record, if any. */
    currentUrl?: string | null;
    /** Called when the user clears the stored file. Omit to forbid removal. */
    onRemoveCurrent?: () => void;
    /** Set once the stored file is marked for removal. */
    currentRemoved?: boolean;
}

/**
 * A single-file picker with an inline preview for images.
 *
 * Deliberately uncontrolled at the DOM level: a file input's value cannot be
 * set programmatically, so the staged File lives in React state and the input
 * is only ever read from or reset.
 */
export function FileField({
    id, label, value, onChange, error, accept, hint, currentUrl, onRemoveCurrent, currentRemoved,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const describedBy = useId();

    // Object URLs leak until revoked, so tie the lifetime to the staged file.
    useEffect(() => {
        if (!value || !value.type.startsWith('image/')) {
            setPreviewUrl(null);
            return;
        }

        const url = URL.createObjectURL(value);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [value]);

    const clearStaged = () => {
        onChange(null);
        if (inputRef.current) inputRef.current.value = '';
    };

    const shownUrl = previewUrl ?? (currentRemoved ? null : currentUrl ?? null);
    const hasStored = !currentRemoved && !!currentUrl;

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>

            <div className="flex items-start gap-4">
                {shownUrl && (
                    <img src={shownUrl} alt={value ? value.name : label}
                        className="h-16 w-16 rounded-md border object-contain" />
                )}

                <div className="space-y-2">
                    <input
                        ref={inputRef}
                        id={id}
                        type="file"
                        accept={accept}
                        aria-invalid={!!error}
                        aria-describedby={hint ? describedBy : undefined}
                        onChange={(e) => onChange(e.target.files?.[0] ?? null)}
                        className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-accent"
                    />

                    <div className="flex gap-2">
                        {value && (
                            <Button type="button" variant="ghost" size="sm" onClick={clearStaged}>
                                <Upload className="mr-1 h-3.5 w-3.5" />
                                Discard selection
                            </Button>
                        )}
                        {hasStored && !value && onRemoveCurrent && (
                            <Button type="button" variant="ghost" size="sm" onClick={onRemoveCurrent}>
                                <Trash2 className="mr-1 h-3.5 w-3.5" />
                                Remove
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            {hint && <p id={describedBy} className="text-sm text-muted-foreground">{hint}</p>}
            <FormError message={error} />
        </div>
    );
}
