import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

export type CatalogueSection = { section: string; label: string; permissions: { value: string; label: string }[] };
export type PermissionState = 1 | -1;
export type PermissionMap = Record<string, PermissionState>;
export type EffectivePermission = { granted: boolean; via: string | null };

export interface PermissionMatrixProps {
    catalogue: CatalogueSection[];
    mode: 'grant' | 'tristate';
    value: Record<string, 1 | -1>;
    onChange(v: Record<string, 1 | -1>): void;
    effective?: Record<string, EffectivePermission>;
    hidePrivileged?: boolean;
}

const PRIVILEGED = ['superuser', 'admin'];
const isPrivileged = (key: string) => PRIVILEGED.includes(key);

const TRISTATE: { label: string; state: PermissionState | null }[] = [
    { label: 'Inherit', state: null },
    { label: 'Grant', state: 1 },
    { label: 'Deny', state: -1 },
];

function effectiveText(e?: EffectivePermission): string | null {
    if (!e) return null;
    return e.granted ? `Granted${e.via ? ` by ${e.via}` : ''}` : 'Not granted';
}

export function PermissionMatrix({ catalogue, mode, value, onChange, effective, hidePrivileged = false }: PermissionMatrixProps) {
    const set = (key: string, state: PermissionState | null) => {
        const next = { ...value };
        if (state === null) delete next[key];
        else next[key] = state;
        onChange(next);
    };

    const toggleAll = (keys: string[]) => {
        const next = { ...value };
        const allGranted = keys.every((k) => next[k] === 1);
        for (const k of keys) {
            if (allGranted) delete next[k];
            else next[k] = 1;
        }
        onChange(next);
    };

    return (
        <div className="divide-y rounded-md border">
            {catalogue.map((section) => {
                const permissions = section.permissions.filter((p) => !(hidePrivileged && isPrivileged(p.value)));
                if (permissions.length === 0) return null;
                // Superuser/admin are never part of "all"; they are granted deliberately.
                const bulkKeys = permissions.filter((p) => !isPrivileged(p.value)).map((p) => p.value);
                const allGranted = bulkKeys.length > 0 && bulkKeys.every((k) => value[k] === 1);

                return (
                    <div key={section.section} className="grid gap-3 p-4 md:grid-cols-[12rem_1fr]" data-section={section.section}>
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">{section.label}</span>
                            {bulkKeys.length > 0 && (
                                <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Checkbox
                                        aria-label={`All ${section.label}`}
                                        checked={allGranted}
                                        onCheckedChange={() => toggleAll(bulkKeys)}
                                    />
                                    all
                                </label>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-x-6 gap-y-3">
                            {permissions.map((p) => {
                                const name = `${section.label} ${p.label}`;
                                const privileged = isPrivileged(p.value);
                                const current = value[p.value] ?? null;
                                const effectiveLabel = mode === 'tristate' ? effectiveText(effective?.[p.value]) : null;
                                return (
                                    <div key={p.value} className="flex flex-col gap-1">
                                        {mode === 'grant' ? (
                                            <label className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    aria-label={name}
                                                    checked={current === 1}
                                                    onCheckedChange={(c) => set(p.value, c ? 1 : null)}
                                                />
                                                {p.label}
                                            </label>
                                        ) : (
                                            <>
                                                <span className="text-sm">{p.label}</span>
                                                <div role="radiogroup" aria-label={name} className="inline-flex overflow-hidden rounded-md border text-xs">
                                                    {TRISTATE.map((opt) => (
                                                        <button
                                                            key={opt.label}
                                                            type="button"
                                                            role="radio"
                                                            aria-checked={current === opt.state}
                                                            onClick={() => set(p.value, opt.state)}
                                                            className={cn(
                                                                'px-2 py-1 transition-colors',
                                                                current === opt.state
                                                                    ? opt.state === -1
                                                                        ? 'bg-destructive text-white'
                                                                        : opt.state === 1
                                                                          ? 'bg-primary text-primary-foreground'
                                                                          : 'bg-muted font-medium'
                                                                    : 'hover:bg-accent',
                                                            )}
                                                        >
                                                            {opt.label}
                                                        </button>
                                                    ))}
                                                </div>
                                                {effectiveLabel && <span className="text-xs text-muted-foreground">{effectiveLabel}</span>}
                                            </>
                                        )}
                                        {privileged && p.value === 'superuser' && (
                                            <span className="max-w-56 text-xs text-amber-600">Full access to everything, including settings and groups.</span>
                                        )}
                                        {privileged && p.value === 'admin' && (
                                            <span className="max-w-56 text-xs text-amber-600">Everything except superuser-only actions.</span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
