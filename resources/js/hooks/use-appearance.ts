import { useCallback, useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const prefersDark = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-color-scheme: dark)').matches;

export function applyAppearance(appearance: Appearance) {
    const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark());
    document.documentElement.classList.toggle('dark', isDark);
}

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>('system');

    const updateAppearance = useCallback((value: Appearance) => {
        setAppearance(value);
        localStorage.setItem('appearance', value);
        applyAppearance(value);
    }, []);

    useEffect(() => {
        const saved = (localStorage.getItem('appearance') as Appearance) ?? 'system';
        setAppearance(saved);
        applyAppearance(saved);
    }, []);

    return { appearance, updateAppearance } as const;
}
