// Placeholder only: Task 6 ("Frontend consolidation") replaces this file wholesale by
// porting `people/show.tsx`. This stub exists solely so Inertia's `assertInertia`
// page-existence check (`inertia.testing.ensure_pages_exist`, true by default and
// unmodified in this app's config) doesn't fail Task 5's `UserController::show` test
// before the real UI lands.
interface AssetRow {
    id: string;
    model_name: string | null;
    serial_number: string | null;
    state: string;
    state_label: string | null;
    place_name: string | null;
}

interface Props {
    user: {
        id: string;
        name: string | null;
        firstname: string;
        lastname: string;
        email: string | null;
        login_enabled: boolean;
        assets_count: number;
    };
    assets: AssetRow[];
}

export default function UserShow({ user }: Props) {
    return <div>{user.name}</div>;
}
