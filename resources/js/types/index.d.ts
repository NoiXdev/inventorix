export interface User {
    id: string;
    name: string | null;
    email: string | null;
}

export interface PageProps {
    auth: { user: User | null };
    flash: { success: string | null; error: string | null; warning: string | null };
    /** Status labels shown under Assets in the sidebar. */
    statusNav: { id: string; name: string | null; count: number }[];
    [key: string]: unknown;
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
}
