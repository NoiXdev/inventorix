export interface Depreciation {
    id: string;
    name: string;
    months: number;
    depreciation_min: string;
    fiscal_year_start_month: number;
    rate_multiplier: string;
    depreciation_type: string;
}

export type Option = { value: string; label: string };

export const MONTH_OPTIONS: Option[] = [
    'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
].map((label, i) => ({ value: String(i + 1), label }));
