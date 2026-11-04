import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/** A card grouping a section of a form: a header area and a content area, nothing else. */
export function FormCard({ className, ...props }: React.ComponentProps<'div'>) {
    return <Card data-slot="form-card" className={cn('gap-0 py-0', className)} {...props} />;
}

interface HeaderProps extends Omit<React.ComponentProps<'div'>, 'title'> {
    title?: React.ReactNode;
    description?: React.ReactNode;
}

export function FormCardHeader({ title, description, className, children, ...props }: HeaderProps) {
    return (
        <CardHeader data-slot="form-card-header" className={cn('border-b py-4 [.border-b]:pb-4', className)} {...props}>
            {title && <CardTitle>{title}</CardTitle>}
            {description && <CardDescription>{description}</CardDescription>}
            {children}
        </CardHeader>
    );
}

export function FormCardContent({ className, ...props }: React.ComponentProps<'div'>) {
    return <CardContent data-slot="form-card-content" className={cn('grid gap-6 py-6', className)} {...props} />;
}
