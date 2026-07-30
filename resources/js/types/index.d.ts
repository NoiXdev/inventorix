export interface User {
    id: string;
    name: string | null;
    email: string | null;
}

export interface PageProps {
    auth: { user: User | null };
    flash: { success: string | null; error: string | null; warning: string | null };
    [key: string]: unknown;
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
}
