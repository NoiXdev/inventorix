import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { GeneralTab } from './tabs/general-tab';
import { MailTab } from './tabs/mail-tab';
import { StorageTab } from './tabs/storage-tab';
import { WarrantyTab } from './tabs/warranty-tab';
import { AuthTab } from './tabs/auth-tab';
import { AlertsTab, type AlertValues } from './tabs/alerts-tab';

interface Props {
    t: any;
    options: { mailDrivers: { value: string; label: string }[]; mailSchemes: { value: string; label: string }[] };
    settings: {
        general: { app_name: string };
        mail: Record<string, string | number | null>;
        storage: Record<string, string | boolean | null>;
        warranty: { enabled: boolean; recipients: string[]; lead_days: string[] };
        alerts: AlertValues;
        auth: Record<string, string | boolean | null>;
    };
}

export default function Settings({ t, options, settings }: Props) {
    return (
        <AppLayout title="Settings" breadcrumbs={[{ label: 'Settings' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Settings</h1>
                <Button asChild variant="outline"><Link href="/app/groups">Groups</Link></Button>
            </div>
            <Tabs defaultValue="general">
                <TabsList>
                    <TabsTrigger value="general">{t.general.title}</TabsTrigger>
                    <TabsTrigger value="mail">{t.mail.title}</TabsTrigger>
                    <TabsTrigger value="storage">{t.storage.title}</TabsTrigger>
                    <TabsTrigger value="warranty">{t.warranty.title}</TabsTrigger>
                    <TabsTrigger value="alerts">{t.alerts.title}</TabsTrigger>
                    <TabsTrigger value="auth">{t.auth.title}</TabsTrigger>
                </TabsList>
                <TabsContent value="general" className="pt-4"><GeneralTab t={t} values={settings.general} /></TabsContent>
                <TabsContent value="mail" className="pt-4"><MailTab t={t} values={settings.mail} drivers={options.mailDrivers} schemes={options.mailSchemes} /></TabsContent>
                <TabsContent value="storage" className="pt-4"><StorageTab t={t} values={settings.storage} /></TabsContent>
                <TabsContent value="warranty" className="pt-4"><WarrantyTab t={t} values={settings.warranty} /></TabsContent>
                <TabsContent value="alerts" className="pt-4"><AlertsTab t={t} values={settings.alerts} /></TabsContent>
                <TabsContent value="auth" className="pt-4"><AuthTab t={t} values={settings.auth} /></TabsContent>
            </Tabs>
        </AppLayout>
    );
}
