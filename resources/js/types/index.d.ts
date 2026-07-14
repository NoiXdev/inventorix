export interface User {
    id: string;
    name: string;
    firstname: string | null;
    lastname: string | null;
    email: string;
}

export interface PageProps {
    auth: { user: User | null };
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
}
