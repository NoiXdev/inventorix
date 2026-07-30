export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface FilterConfig {
    key: string;
    label: string;
    options: { value: string; label: string }[];
}
