export interface Group {
    id: string;
    name: string;
    notes: string | null;
    permissions: Record<string, 1 | -1>;
}
