# Inertia Migration — Foundation & Template Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the Filament panel to `/app-old` and stand up a working React/Inertia app at `/app` — app shell, design system, a reusable server-driven DataTable and CRUD form kit, shared session auth, and Manufacturers migrated end-to-end as the reference implementation.

**Architecture:** Strangler-in-place. Filament and the new Inertia app run side by side in the same repo against the same DB, session, and web guard. Inertia's frontend assets are a **separate Vite entry** so Filament's compiled CSS/JS is never touched. The DataTable resolves sort/search/filter/pagination from the URL query on the server via a reusable `TableQuery` helper; forms use react-hook-form + zod with Laravel validation as the source of truth.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v2 (`inertiajs/inertia-laravel` + `@inertiajs/react`), React 19, TypeScript, Tailwind 4 (`@tailwindcss/vite`), shadcn/ui (neutral/zinc), TanStack Table, react-hook-form, zod, Ziggy, Pest, Vitest. All commands run via `ddev exec …`.

## Global Constraints

- All `pnpm` / `node` / `php` / `artisan` commands run through ddev, e.g. `ddev exec pnpm add x`, `ddev artisan migrate`, `ddev php`.
- Do **not** modify Filament's Vite inputs (`resources/css/app.css`, `resources/js/plugins/*`) or `AppPanelProvider` beyond the single `->path()` change in Task 1.
- Filament panel **id** stays `app`; only its URL **path** changes to `app-old`. All `filament.app.*` route names must keep resolving.
- New Inertia app lives under the `/app` URL prefix. New React source lives under `resources/js/` (`@/*` alias). New server code lives under `App\Http\Controllers\App` and `App\Support\Table`.
- Models are UUID primary keys (`HasUuids`). No database schema changes in this spec.
- No per-resource policy for Manufacturers (none exists today); gate on the `auth` middleware only.
- TDD: write the failing test first for all server logic and table/controller/auth behavior. Commit after every task.
- **PHP tests use PHPUnit, not Pest** (there is no Pest in this repo). Write test files as PHPUnit classes extending `Tests\TestCase` with `public function test_*(): void` methods and `$this->assert*` / `$this->assertInertia(...)`, matching the existing `tests/` convention. Any `it(...)`/`expect(...)` shown in a task's code block is a sketch of the *assertions* to make — translate it to PHPUnit method form. Run PHP tests with `ddev exec ./vendor/bin/phpunit <path>` (full suite: `ddev exec php artisan test`).

---

### Task 1: Move Filament panel to `/app-old`

**Files:**
- Modify: `app/Providers/Filament/AppPanelProvider.php` (the `->path('app')` call)
- Test: `tests/Feature/FilamentRelocationTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: Filament panel served at `/app-old`; route names unchanged (`filament.app.auth.login` → `/app-old/login`).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/FilamentRelocationTest.php

it('serves the filament login at the new /app-old path', function () {
    $this->get('/app-old/login')->assertOk();
});

it('no longer serves filament at the old /app path', function () {
    // /app is reserved for the new Inertia app; the Filament login must not answer here.
    $this->get('/app/login')->assertNotFound();
});

it('keeps the filament.app.* route names resolving', function () {
    expect(route('filament.app.auth.login'))->toContain('/app-old/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/FilamentRelocationTest.php`
Expected: FAIL — `/app-old/login` returns 404 (still at `/app`).

- [ ] **Step 3: Change the panel path**

In `app/Providers/Filament/AppPanelProvider.php`, change the single line:

```php
->id('app')
->path('app-old')
```

(Leave `->id('app')`, `->default()`, and everything else untouched.)

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/FilamentRelocationTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Manually verify**

Run: `ddev exec php artisan route:list --path=app-old | head` — confirm Filament routes now under `app-old`. Load `https://<ddev-url>/app-old` in a browser: the Filament panel renders unchanged.

- [ ] **Step 6: Commit**

```bash
git add app/Providers/Filament/AppPanelProvider.php tests/Feature/FilamentRelocationTest.php
git commit -m "refactor(filament): relocate panel from /app to /app-old"
```

---

### Task 2: Install Inertia + React and render a smoke page at `/app`

**Files:**
- Modify: `composer.json` (via require), `package.json` (via add), `vite.config.ts`, `tsconfig.json`, `bootstrap/app.php`, `routes/web.php`
- Create: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/views/app.blade.php`, `resources/js/app.tsx`, `resources/css/app-inertia.css`, `resources/js/pages/smoke.tsx`
- Test: `tests/Feature/InertiaSmokeTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `createInertiaApp` bootstrapped from `resources/js/app.tsx`; Inertia pages resolved from `resources/js/pages/*.tsx`; a `GET /app` route returning Inertia page `smoke`. (This route is temporarily unauthenticated; Task 10 adds the auth guard.)

- [ ] **Step 1: Install server + client dependencies**

```bash
ddev composer require inertiajs/inertia-laravel
ddev exec pnpm add @inertiajs/react react react-dom
ddev exec pnpm add -D @vitejs/plugin-react @types/react @types/react-dom
```

(Ziggy is intentionally **not** installed in Spec 1 — the frontend uses plain
`/app/...` string URLs. Add it in a later spec if route volume justifies it.)

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Feature/InertiaSmokeTest.php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the inertia smoke page at /app', function () {
    $this->get('/app')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('smoke'));
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/InertiaSmokeTest.php`
Expected: FAIL — route `/app` not defined (404) / component assertion unmet.

- [ ] **Step 4: Create the Inertia middleware**

```php
<?php // app/Http/Middleware/HandleInertiaRequests.php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? $request->user()->only('id', 'name', 'firstname', 'lastname', 'email')
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
```

- [ ] **Step 5: Register the middleware on the web group**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware): void {`, append to the existing `$middleware->web(append: [...])` array so it reads:

```php
$middleware->web(append: [
    SecureHeaders::class,
    \App\Http\Middleware\HandleInertiaRequests::class,
]);
```

- [ ] **Step 6: Create the Inertia root view**

```blade
{{-- resources/views/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Inventorix') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app-inertia.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="h-full bg-background text-foreground antialiased">
    @inertia
</body>
</html>
```

- [ ] **Step 7: Create the Inertia CSS entry (base tokens; full theme added in Task 3)**

```css
/* resources/css/app-inertia.css */
@import 'tailwindcss';

@layer base {
    :root { color-scheme: light; }
    .dark { color-scheme: dark; }
}
```

- [ ] **Step 8: Create the client bootstrap**

```tsx
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} · Inventorix` : 'Inventorix'),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#18181b' },
});
```

- [ ] **Step 9: Create the smoke page**

```tsx
// resources/js/pages/smoke.tsx
export default function Smoke() {
    return <div className="p-8 text-2xl font-semibold">Inertia is live.</div>;
}
```

- [ ] **Step 10: Wire Vite and TypeScript**

Add the React plugin and Inertia inputs in `vite.config.ts` (keep the existing Filament inputs):

```ts
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/plugins/scanner.ts',
                'resources/js/plugins/qr-print/index.ts',
                // Inertia app
                'resources/css/app-inertia.css',
                'resources/js/app.tsx',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
});
```

In `tsconfig.json`, change `"jsx": "preserve"` to `"jsx": "react-jsx"` and extend `include` to cover `.tsx`:

```json
"include": ["resources/js/**/*.ts", "resources/js/**/*.tsx", "resources/js/**/*.d.ts"]
```

- [ ] **Step 11: Add the `/app` smoke route**

In `routes/web.php`, add (temporarily unauthenticated — Task 10 moves it behind `auth`):

```php
use Inertia\Inertia;

Route::prefix('app')->group(function () {
    Route::get('/', fn () => Inertia::render('smoke'))->name('app.smoke');
});
```

- [ ] **Step 12: Build, then run tests**

Run: `ddev exec pnpm run build`
Expected: build succeeds, emits `app-inertia` + `app` bundles.
Run: `ddev exec ./vendor/bin/phpunit tests/Feature/InertiaSmokeTest.php`
Expected: PASS.

- [ ] **Step 13: Manually verify both apps**

Load `/app` → "Inertia is live." renders. Load `/app-old` → Filament still renders unchanged.

- [ ] **Step 14: Commit**

```bash
git add composer.json composer.lock package.json pnpm-lock.yaml vite.config.ts tsconfig.json bootstrap/app.php routes/web.php app/Http/Middleware/HandleInertiaRequests.php resources/views/app.blade.php resources/js/app.tsx resources/css/app-inertia.css resources/js/pages/smoke.tsx tests/Feature/InertiaSmokeTest.php
git commit -m "feat(inertia): install Inertia+React and render smoke page at /app"
```

---

### Task 3: shadcn/ui + neutral theme with light/dark

**Files:**
- Create: `components.json`, `resources/js/lib/utils.ts`, shadcn component files under `resources/js/components/ui/`, `resources/js/hooks/use-appearance.ts`
- Modify: `resources/css/app-inertia.css` (theme tokens + dark variant), `resources/views/app.blade.php` (inline appearance script)

**Interfaces:**
- Consumes: Tailwind 4 pipeline from Task 2.
- Produces: `cn()` from `@/lib/utils`; UI primitives importable from `@/components/ui/*`; `useAppearance()` returning `{ appearance, updateAppearance }` with `'light' | 'dark' | 'system'`; `.dark` class toggled on `<html>`.

- [ ] **Step 1: Add the shadcn theme tokens and dark variant to the CSS entry**

Replace `resources/css/app-inertia.css` with the neutral/zinc token set (oklch), using the Tailwind 4 `@custom-variant` dark strategy:

```css
/* resources/css/app-inertia.css */
@import 'tailwindcss';

@custom-variant dark (&:where(.dark, .dark *));

:root {
    --radius: 0.625rem;
    --background: oklch(1 0 0);
    --foreground: oklch(0.145 0 0);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.145 0 0);
    --popover: oklch(1 0 0);
    --popover-foreground: oklch(0.145 0 0);
    --primary: oklch(0.205 0 0);
    --primary-foreground: oklch(0.985 0 0);
    --secondary: oklch(0.97 0 0);
    --secondary-foreground: oklch(0.205 0 0);
    --muted: oklch(0.97 0 0);
    --muted-foreground: oklch(0.556 0 0);
    --accent: oklch(0.97 0 0);
    --accent-foreground: oklch(0.205 0 0);
    --destructive: oklch(0.577 0.245 27.325);
    --destructive-foreground: oklch(0.985 0 0);
    --border: oklch(0.922 0 0);
    --input: oklch(0.922 0 0);
    --ring: oklch(0.708 0 0);
}

.dark {
    --background: oklch(0.145 0 0);
    --foreground: oklch(0.985 0 0);
    --card: oklch(0.205 0 0);
    --card-foreground: oklch(0.985 0 0);
    --popover: oklch(0.205 0 0);
    --popover-foreground: oklch(0.985 0 0);
    --primary: oklch(0.985 0 0);
    --primary-foreground: oklch(0.205 0 0);
    --secondary: oklch(0.269 0 0);
    --secondary-foreground: oklch(0.985 0 0);
    --muted: oklch(0.269 0 0);
    --muted-foreground: oklch(0.708 0 0);
    --accent: oklch(0.269 0 0);
    --accent-foreground: oklch(0.985 0 0);
    --destructive: oklch(0.704 0.191 22.216);
    --destructive-foreground: oklch(0.985 0 0);
    --border: oklch(1 0 0 / 10%);
    --input: oklch(1 0 0 / 15%);
    --ring: oklch(0.556 0 0);
}

@theme inline {
    --color-background: var(--background);
    --color-foreground: var(--foreground);
    --color-card: var(--card);
    --color-card-foreground: var(--card-foreground);
    --color-popover: var(--popover);
    --color-popover-foreground: var(--popover-foreground);
    --color-primary: var(--primary);
    --color-primary-foreground: var(--primary-foreground);
    --color-secondary: var(--secondary);
    --color-secondary-foreground: var(--secondary-foreground);
    --color-muted: var(--muted);
    --color-muted-foreground: var(--muted-foreground);
    --color-accent: var(--accent);
    --color-accent-foreground: var(--accent-foreground);
    --color-destructive: var(--destructive);
    --color-destructive-foreground: var(--destructive-foreground);
    --color-border: var(--border);
    --color-input: var(--input);
    --color-ring: var(--ring);
    --radius-lg: var(--radius);
    --radius-md: calc(var(--radius) - 2px);
    --radius-sm: calc(var(--radius) - 4px);
}

@layer base {
    * { @apply border-border; }
    body { @apply bg-background text-foreground; }
}
```

- [ ] **Step 2: Create the shadcn config**

```json
// components.json
{
  "$schema": "https://ui.shadcn.com/schema.json",
  "style": "new-york",
  "rsc": false,
  "tsx": true,
  "tailwind": {
    "config": "",
    "css": "resources/css/app-inertia.css",
    "baseColor": "neutral",
    "cssVariables": true,
    "prefix": ""
  },
  "aliases": {
    "components": "@/components",
    "utils": "@/lib/utils",
    "ui": "@/components/ui",
    "lib": "@/lib",
    "hooks": "@/hooks"
  }
}
```

- [ ] **Step 3: Create the `cn` utility**

```ts
// resources/js/lib/utils.ts
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
```

Install its deps:

```bash
ddev exec pnpm add clsx tailwind-merge class-variance-authority lucide-react tailwindcss-animate
```

- [ ] **Step 4: Generate the UI primitives via the shadcn CLI**

Run (answer prompts using the existing `components.json`):

```bash
ddev exec pnpm dlx shadcn@latest add button input label select checkbox dialog dropdown-menu sheet table badge sonner tooltip avatar card skeleton separator
```

Expected: files created under `resources/js/components/ui/`.

- [ ] **Step 5: Create the appearance hook**

```ts
// resources/js/hooks/use-appearance.ts
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
```

- [ ] **Step 6: Prevent flash-of-wrong-theme (inline script in root view)**

In `resources/views/app.blade.php`, add inside `<head>` **before** `@vite`:

```blade
<script>
    (function () {
        const a = localStorage.getItem('appearance') || 'system';
        const dark = a === 'dark' || (a === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>
```

- [ ] **Step 7: Build to verify the theme compiles**

Run: `ddev exec pnpm run build`
Expected: build succeeds; `@/components/ui/button` etc. resolve without type errors.

- [ ] **Step 8: Commit**

```bash
git add components.json resources/js/lib resources/js/components/ui resources/js/hooks resources/css/app-inertia.css resources/views/app.blade.php package.json pnpm-lock.yaml
git commit -m "feat(inertia): add shadcn/ui neutral theme with light/dark appearance"
```

---

### Task 4: App shell (sidebar, top bar, theme toggle) + dashboard placeholder

**Files:**
- Create: `resources/js/config/nav.ts`, `resources/js/types/index.d.ts`, `resources/js/layouts/app-layout.tsx`, `resources/js/components/app-sidebar.tsx`, `resources/js/components/app-topbar.tsx`, `resources/js/components/theme-toggle.tsx`, `resources/js/components/user-menu.tsx`, `resources/js/components/breadcrumbs.tsx`, `resources/js/pages/dashboard.tsx`
- Modify: `routes/web.php` (point `/app` at `dashboard`)

**Interfaces:**
- Consumes: `@/components/ui/*`, `useAppearance()`, shared `auth.user` prop.
- Produces: `AppLayout` (props: `{ title: string; breadcrumbs?: {label:string; href?:string}[]; children: ReactNode }`); `navGroups: NavGroup[]` from `@/config/nav`; `PageProps` type with `auth.user`.

- [ ] **Step 1: Define shared TS types**

```ts
// resources/js/types/index.d.ts
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
```

- [ ] **Step 2: Define the navigation config**

```ts
// resources/js/config/nav.ts
import { LayoutDashboard, Boxes, Factory } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    match: (path: string) => boolean;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

// Only the areas that exist in Spec 1. Later specs append here.
export const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', href: '/app', icon: LayoutDashboard, match: (p) => p === '/app' },
        ],
    },
    {
        label: 'Inventory',
        items: [
            { label: 'Manufacturers', href: '/app/manufacturers', icon: Factory, match: (p) => p.startsWith('/app/manufacturers') },
        ],
    },
];
```

(`Boxes` is imported for later reuse; if the linter flags it unused, drop it.)

- [ ] **Step 3: Create the theme toggle**

```tsx
// resources/js/components/theme-toggle.tsx
import { Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';

export function ThemeToggle() {
    const { updateAppearance } = useAppearance();
    const options: [Appearance, string, typeof Sun][] = [
        ['light', 'Light', Sun],
        ['dark', 'Dark', Moon],
        ['system', 'System', Monitor],
    ];
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Toggle theme">
                    <Sun className="h-5 w-5 dark:hidden" />
                    <Moon className="hidden h-5 w-5 dark:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {options.map(([value, label, Icon]) => (
                    <DropdownMenuItem key={value} onClick={() => updateAppearance(value)}>
                        <Icon className="mr-2 h-4 w-4" /> {label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

- [ ] **Step 4: Create the user menu**

```tsx
// resources/js/components/user-menu.tsx
import { router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PageProps } from '@/types';

export function UserMenu() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;
    const initials = user ? (user.name || user.email).slice(0, 2).toUpperCase() : '?';
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="rounded-full" aria-label="Account">
                    <Avatar className="h-8 w-8"><AvatarFallback>{initials}</AvatarFallback></Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="truncate">{user?.email}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => router.post('/app/logout')}>
                    <LogOut className="mr-2 h-4 w-4" /> Log out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

(The `/app/logout` route is created in Task 10; the menu renders fine before then.)

- [ ] **Step 5: Create the sidebar**

```tsx
// resources/js/components/app-sidebar.tsx
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { navGroups } from '@/config/nav';

export function AppSidebar() {
    const path = usePage().url.split('?')[0];
    return (
        <aside className="hidden w-60 shrink-0 border-r bg-card md:flex md:flex-col">
            <div className="flex h-14 items-center border-b px-4 font-semibold">Inventorix</div>
            <nav className="flex-1 space-y-6 overflow-y-auto p-3">
                {navGroups.map((group) => (
                    <div key={group.label}>
                        <div className="px-2 pb-1 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            {group.label}
                        </div>
                        <ul className="space-y-1">
                            {group.items.map((item) => {
                                const active = item.match(path);
                                const Icon = item.icon;
                                return (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm',
                                                active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                            )}
                                        >
                                            <Icon className="h-4 w-4" /> {item.label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>
        </aside>
    );
}
```

- [ ] **Step 6: Create breadcrumbs and top bar**

```tsx
// resources/js/components/breadcrumbs.tsx
import { Link } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';

export function Breadcrumbs({ items }: { items: BreadcrumbItem[] }) {
    return (
        <nav className="flex items-center gap-1 text-sm text-muted-foreground">
            {items.map((item, i) => (
                <span key={i} className="flex items-center gap-1">
                    {i > 0 && <span>/</span>}
                    {item.href ? (
                        <Link href={item.href} className="hover:text-foreground">{item.label}</Link>
                    ) : (
                        <span className="text-foreground">{item.label}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}
```

```tsx
// resources/js/components/app-topbar.tsx
import { Input } from '@/components/ui/input';
import { ThemeToggle } from '@/components/theme-toggle';
import { UserMenu } from '@/components/user-menu';
import { Breadcrumbs } from '@/components/breadcrumbs';
import type { BreadcrumbItem } from '@/types';

export function AppTopbar({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <header className="flex h-14 items-center gap-4 border-b bg-card px-4">
            <div className="flex-1"><Breadcrumbs items={breadcrumbs} /></div>
            <Input placeholder="Search…" className="hidden w-64 lg:block" disabled />
            <ThemeToggle />
            <UserMenu />
        </header>
    );
}
```

(The search input is intentionally a disabled stub — real search is a later spec.)

- [ ] **Step 7: Create the layout**

```tsx
// resources/js/layouts/app-layout.tsx
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { AppSidebar } from '@/components/app-sidebar';
import { AppTopbar } from '@/components/app-topbar';
import type { BreadcrumbItem } from '@/types';

interface Props {
    title: string;
    breadcrumbs?: BreadcrumbItem[];
    children: ReactNode;
}

export default function AppLayout({ title, breadcrumbs, children }: Props) {
    return (
        <div className="flex h-screen overflow-hidden">
            <Head title={title} />
            <AppSidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                <AppTopbar breadcrumbs={breadcrumbs} />
                <main className="flex-1 overflow-y-auto p-6">{children}</main>
            </div>
            <Toaster richColors position="top-right" />
        </div>
    );
}
```

- [ ] **Step 8: Create the dashboard page and point `/app` at it**

```tsx
// resources/js/pages/dashboard.tsx
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function Dashboard() {
    return (
        <AppLayout title="Dashboard" breadcrumbs={[{ label: 'Dashboard' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Dashboard</h1>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {['Assets', 'Handovers', 'Open incidents', 'Users'].map((label) => (
                    <Card key={label}>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{label}</CardTitle></CardHeader>
                        <CardContent className="text-2xl font-semibold">—</CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
```

In `routes/web.php`, change the smoke route to render `dashboard`:

```php
Route::prefix('app')->group(function () {
    Route::get('/', fn () => Inertia::render('dashboard'))->name('app.dashboard');
});
```

Delete `resources/js/pages/smoke.tsx` and update `tests/Feature/InertiaSmokeTest.php` to assert `->component('dashboard')`.

- [ ] **Step 9: Build and test**

Run: `ddev exec pnpm run build && ddev exec ./vendor/bin/phpunit tests/Feature/InertiaSmokeTest.php`
Expected: build succeeds; test PASS.

- [ ] **Step 10: Manually verify shell + theme**

Load `/app`: sidebar with Overview/Inventory groups, top bar with theme toggle + avatar, four KPI cards. Toggle light/dark/system — colors switch with no flash on reload.

- [ ] **Step 11: Commit**

```bash
git add resources/js routes/web.php tests/Feature/InertiaSmokeTest.php
git rm resources/js/pages/smoke.tsx
git commit -m "feat(inertia): app shell (sidebar, topbar, theme toggle) + dashboard"
```

---

### Task 5: Server-side `TableQuery` helper

**Files:**
- Create: `app/Support/Table/TableQuery.php`
- Test: `tests/Feature/Support/TableQueryTest.php`

**Interfaces:**
- Consumes: an Eloquent `Builder`, the current `Request`.
- Produces: `TableQuery::for(Builder $query, Request $request)` → chainable `->searchable(array $columns)`, `->sortable(array $columns)`, `->paginate(int $default = 15)` returning a `LengthAwarePaginator` with query string appended. Reads request keys: `search` (string), `sort` (e.g. `name` or `-name` for desc), `perPage` (int), `page` (int).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Support/TableQueryTest.php

use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Http\Request;

it('filters by search across whitelisted columns', function () {
    Manufacturer::factory()->create(['name' => 'Acme Corp']);
    Manufacturer::factory()->create(['name' => 'Globex']);

    $request = Request::create('/app/manufacturers', 'GET', ['search' => 'acme']);
    $result = TableQuery::for(Manufacturer::query(), $request)
        ->searchable(['name'])->sortable(['name'])->paginate();

    expect($result->total())->toBe(1)
        ->and($result->first()->name)->toBe('Acme Corp');
});

it('sorts descending when the sort key is prefixed with a dash', function () {
    Manufacturer::factory()->create(['name' => 'Alpha']);
    Manufacturer::factory()->create(['name' => 'Zulu']);

    $request = Request::create('/app/manufacturers', 'GET', ['sort' => '-name']);
    $result = TableQuery::for(Manufacturer::query(), $request)
        ->sortable(['name'])->paginate();

    expect($result->first()->name)->toBe('Zulu');
});

it('ignores sort columns that are not whitelisted', function () {
    Manufacturer::factory()->count(2)->create();

    $request = Request::create('/app/manufacturers', 'GET', ['sort' => 'secret_column']);
    $result = TableQuery::for(Manufacturer::query(), $request)
        ->sortable(['name'])->paginate();

    expect($result->total())->toBe(2); // no exception, sort ignored
});

it('respects the perPage parameter and appends the query string', function () {
    Manufacturer::factory()->count(30)->create();

    $request = Request::create('/app/manufacturers', 'GET', ['perPage' => '10', 'search' => 'x']);
    $result = TableQuery::for(Manufacturer::query(), $request)
        ->searchable(['name'])->paginate();

    expect($result->perPage())->toBe(10)
        ->and($result->url(2))->toContain('search=x');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/Support/TableQueryTest.php`
Expected: FAIL — class `App\Support\Table\TableQuery` not found.

- [ ] **Step 3: Implement `TableQuery`**

```php
<?php // app/Support/Table/TableQuery.php

namespace App\Support\Table;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TableQuery
{
    private array $searchable = [];
    private array $sortable = [];

    private function __construct(
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    public function sortable(array $columns): self
    {
        $this->sortable = $columns;

        return $this;
    }

    public function paginate(int $default = 15): LengthAwarePaginator
    {
        $this->applySearch();
        $this->applySort();

        $perPage = (int) $this->request->integer('perPage', $default);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : $default;

        return $this->query
            ->paginate($perPage)
            ->withQueryString();
    }

    private function applySearch(): void
    {
        $term = trim((string) $this->request->string('search', ''));
        if ($term === '' || $this->searchable === []) {
            return;
        }

        $this->query->where(function (Builder $q) use ($term): void {
            foreach ($this->searchable as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    private function applySort(): void
    {
        $sort = (string) $this->request->string('sort', '');
        if ($sort === '') {
            return;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, $this->sortable, true)) {
            $this->query->orderBy($column, $direction);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/Support/TableQueryTest.php`
Expected: PASS (4 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Table/TableQuery.php tests/Feature/Support/TableQueryTest.php
git commit -m "feat(table): add server-side TableQuery helper (search/sort/paginate)"
```

---

### Task 6: `DataTable` React component

**Files:**
- Create: `resources/js/components/data-table/data-table.tsx`, `resources/js/components/data-table/types.ts`, `resources/js/components/data-table/use-table-query.ts`
- Test: `resources/js/components/data-table/__tests__/use-table-query.test.ts`

**Interfaces:**
- Consumes: `@/components/ui/table`, `@/components/ui/button`, `@/components/ui/input`, `@tanstack/react-table`, Inertia `router`.
- Produces: `DataTable<T>` (props: `{ columns: ColumnDef<T>[]; rows: T[]; pagination: PaginationMeta; searchable?: boolean; baseUrl: string }`); `PaginationMeta` type `{ current_page:number; last_page:number; per_page:number; total:number }`; `buildTableUrl(baseUrl, params)` helper that merges into the current query string.

- [ ] **Step 1: Install TanStack Table**

```bash
ddev exec pnpm add @tanstack/react-table
```

- [ ] **Step 2: Write the failing test for the query-string builder**

```ts
// resources/js/components/data-table/__tests__/use-table-query.test.ts
import { describe, expect, it } from 'vitest';
import { buildTableUrl } from '../use-table-query';

describe('buildTableUrl', () => {
    it('sets a param and resets page to 1 when sort changes', () => {
        const url = buildTableUrl('/app/manufacturers', { search: 'acme' }, '?page=3&sort=name');
        expect(url).toContain('search=acme');
        expect(url).toContain('page=1');
    });

    it('toggles sort direction with a dash prefix', () => {
        const asc = buildTableUrl('/app/manufacturers', { sort: 'name' }, '');
        expect(asc).toContain('sort=name');
        const desc = buildTableUrl('/app/manufacturers', { sort: '-name' }, '?sort=name');
        expect(desc).toContain('sort=-name');
    });

    it('preserves unrelated existing params', () => {
        const url = buildTableUrl('/app/manufacturers', { page: '2' }, '?perPage=25');
        expect(url).toContain('perPage=25');
        expect(url).toContain('page=2');
    });
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/data-table`
Expected: FAIL — cannot resolve `../use-table-query`.

- [ ] **Step 4: Implement the query helper**

```ts
// resources/js/components/data-table/use-table-query.ts
import { router } from '@inertiajs/react';

export function buildTableUrl(
    baseUrl: string,
    params: Record<string, string>,
    currentSearch: string = typeof window !== 'undefined' ? window.location.search : '',
): string {
    const search = new URLSearchParams(currentSearch);
    for (const [key, value] of Object.entries(params)) {
        if (value === '') search.delete(key);
        else search.set(key, value);
    }
    // Any param change other than paging itself resets to page 1.
    if (!('page' in params)) search.set('page', '1');
    return `${baseUrl}?${search.toString()}`;
}

export function visitTable(baseUrl: string, params: Record<string, string>): void {
    router.get(buildTableUrl(baseUrl, params), {}, { preserveState: true, preserveScroll: true, replace: true });
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/data-table`
Expected: PASS (3 passed).

- [ ] **Step 6: Add the shared types**

```ts
// resources/js/components/data-table/types.ts
export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
```

- [ ] **Step 7: Implement the `DataTable` component**

```tsx
// resources/js/components/data-table/data-table.tsx
import { useState } from 'react';
import {
    type ColumnDef, flexRender, getCoreRowModel, useReactTable,
} from '@tanstack/react-table';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { visitTable } from './use-table-query';
import type { PaginationMeta } from './types';

interface Props<T> {
    columns: ColumnDef<T>[];
    rows: T[];
    pagination: PaginationMeta;
    baseUrl: string;
    searchable?: boolean;
}

export function DataTable<T>({ columns, rows, pagination, baseUrl, searchable = true }: Props<T>) {
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const [search, setSearch] = useState(params.get('search') ?? '');
    const table = useReactTable({ data: rows, columns, getCoreRowModel: getCoreRowModel() });

    return (
        <div className="space-y-4">
            {searchable && (
                <form
                    onSubmit={(e) => { e.preventDefault(); visitTable(baseUrl, { search }); }}
                    className="flex gap-2"
                >
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="w-64" />
                    <Button type="submit" variant="secondary">Search</Button>
                </form>
            )}
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((hg) => (
                            <TableRow key={hg.id}>
                                {hg.headers.map((h) => (
                                    <TableHead key={h.id}>
                                        {h.isPlaceholder ? null : flexRender(h.column.columnDef.header, h.getContext())}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow><TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">No results.</TableCell></TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <div className="flex items-center justify-between">
                <div className="text-sm text-muted-foreground">
                    {pagination.total} result{pagination.total === 1 ? '' : 's'}
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" disabled={pagination.current_page <= 1}
                        onClick={() => visitTable(baseUrl, { page: String(pagination.current_page - 1) })}>Previous</Button>
                    <Button variant="outline" size="sm" disabled={pagination.current_page >= pagination.last_page}
                        onClick={() => visitTable(baseUrl, { page: String(pagination.current_page + 1) })}>Next</Button>
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 8: Commit**

```bash
git add resources/js/components/data-table package.json pnpm-lock.yaml
git commit -m "feat(table): add DataTable component with URL-driven paging/search"
```

---

### Task 7: CRUD form kit

**Files:**
- Create: `resources/js/components/form/text-field.tsx`, `resources/js/components/form/form-error.tsx`
- Test: `resources/js/components/form/__tests__/form-error.test.tsx`

**Interfaces:**
- Consumes: `@/components/ui/input`, `@/components/ui/label`.
- Produces: `TextField` (props: `{ id: string; label: string; value: string; onChange: (v: string) => void; error?: string; required?: boolean; autoFocus?: boolean }`); `FormError` (props: `{ message?: string }`) rendering a destructive message when present, nothing when absent. Forms use `useForm` from `@inertiajs/react`; server (Laravel) validation is the source of truth and errors arrive in Inertia's `errors` bag.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/components/form/__tests__/form-error.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FormError } from '../form-error';

describe('FormError', () => {
    it('renders the message when present', () => {
        render(<FormError message="Name is required" />);
        expect(screen.getByText('Name is required')).toBeInTheDocument();
    });

    it('renders nothing when no message', () => {
        const { container } = render(<FormError />);
        expect(container).toBeEmptyDOMElement();
    });
});
```

Install the testing-library deps if not present:

```bash
ddev exec pnpm add -D @testing-library/react @testing-library/jest-dom
```

Ensure `vitest.config.ts` uses the happy-dom environment (already a dependency) and loads `@testing-library/jest-dom`. If a setup file is not yet configured, create `resources/js/test-setup.ts` with `import '@testing-library/jest-dom';` and reference it via `test: { environment: 'happy-dom', setupFiles: ['resources/js/test-setup.ts'] }` in `vitest.config.ts`.

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/form`
Expected: FAIL — cannot resolve `../form-error`.

- [ ] **Step 3: Implement the field components**

```tsx
// resources/js/components/form/form-error.tsx
export function FormError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="text-sm text-destructive">{message}</p>;
}
```

```tsx
// resources/js/components/form/text-field.tsx
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
    required?: boolean;
    autoFocus?: boolean;
}

export function TextField({ id, label, value, onChange, error, required, autoFocus }: Props) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <Input id={id} value={value} autoFocus={autoFocus} aria-invalid={!!error}
                onChange={(e) => onChange(e.target.value)} />
            <FormError message={error} />
        </div>
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/form`
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/form vitest.config.ts resources/js/test-setup.ts package.json pnpm-lock.yaml
git commit -m "feat(form): add CRUD form-kit field components"
```

---

### Task 8: Manufacturers backend (controller, routes, validation)

**Files:**
- Create: `app/Http/Controllers/App/ManufacturerController.php`, `app/Http/Requests/App/ManufacturerRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/App/ManufacturerControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Manufacturer`, `App\Support\Table\TableQuery`.
- Produces: routes named `app.manufacturers.index|create|store|edit|update|destroy` under `/app/manufacturers`; `ManufacturerRequest` validating `name` = `required|string|max:255`. `index` returns Inertia page `manufacturers/index` with props `{ manufacturers: { data, meta } }` where each row is `{ id, name, models_count, assets_count }`. `edit` returns `manufacturers/edit` with `{ manufacturer: { id, name } }`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/App/ManufacturerControllerTest.php

use App\Models\Manufacturer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create(['login_enabled' => true]);
});

it('lists manufacturers with counts', function () {
    Manufacturer::factory()->count(3)->create();

    $this->actingAs($this->user)->get('/app/manufacturers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('manufacturers/index')
            ->has('manufacturers.data', 3)
            ->has('manufacturers.data.0', fn (Assert $row) => $row
                ->has('id')->has('name')->has('models_count')->has('assets_count')
            )
        );
});

it('requires authentication', function () {
    $this->get('/app/manufacturers')->assertRedirect();
});

it('stores a manufacturer', function () {
    $this->actingAs($this->user)
        ->post('/app/manufacturers', ['name' => 'Acme'])
        ->assertRedirect('/app/manufacturers');

    expect(Manufacturer::where('name', 'Acme')->exists())->toBeTrue();
});

it('validates that name is required', function () {
    $this->actingAs($this->user)
        ->post('/app/manufacturers', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('updates a manufacturer', function () {
    $m = Manufacturer::factory()->create(['name' => 'Old']);

    $this->actingAs($this->user)
        ->put("/app/manufacturers/{$m->id}", ['name' => 'New'])
        ->assertRedirect('/app/manufacturers');

    expect($m->fresh()->name)->toBe('New');
});

it('deletes a manufacturer', function () {
    $m = Manufacturer::factory()->create();

    $this->actingAs($this->user)
        ->delete("/app/manufacturers/{$m->id}")
        ->assertRedirect('/app/manufacturers');

    expect(Manufacturer::find($m->id))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/ManufacturerControllerTest.php`
Expected: FAIL — routes/controller not defined.

- [ ] **Step 3: Create the form request**

```php
<?php // app/Http/Requests/App/ManufacturerRequest.php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class ManufacturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/ManufacturerController.php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\ManufacturerRequest;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManufacturerController extends Controller
{
    public function index(Request $request): Response
    {
        $manufacturers = TableQuery::for(
            Manufacturer::query()->withCount('models'),
            $request,
        )->searchable(['name'])->sortable(['name', 'models_count'])->paginate();

        $manufacturers->getCollection()->transform(fn (Manufacturer $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'models_count' => $m->models_count,
            'assets_count' => AssetModel::where('manufacturer_id', $m->id)->withCount('assets')->get()->sum('assets_count'),
        ]);

        return Inertia::render('manufacturers/index', [
            'manufacturers' => [
                'data' => $manufacturers->items(),
                'meta' => [
                    'current_page' => $manufacturers->currentPage(),
                    'last_page' => $manufacturers->lastPage(),
                    'per_page' => $manufacturers->perPage(),
                    'total' => $manufacturers->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('manufacturers/create');
    }

    public function store(ManufacturerRequest $request): RedirectResponse
    {
        Manufacturer::create($request->validated());

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer created.');
    }

    public function edit(Manufacturer $manufacturer): Response
    {
        return Inertia::render('manufacturers/edit', [
            'manufacturer' => ['id' => $manufacturer->id, 'name' => $manufacturer->name],
        ]);
    }

    public function update(ManufacturerRequest $request, Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->update($request->validated());

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer updated.');
    }

    public function destroy(Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->delete();

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer deleted.');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, extend the `/app` group (add `auth` note applied in Task 10; for now the resource routes can sit alongside the dashboard route):

```php
use App\Http\Controllers\App\ManufacturerController;

Route::prefix('app')->name('app.')->group(function () {
    Route::get('/', fn () => Inertia::render('dashboard'))->name('dashboard');
    Route::resource('manufacturers', ManufacturerController::class)->except('show');
});
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/ManufacturerControllerTest.php`
Expected: PASS (6 passed).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/App/ManufacturerController.php app/Http/Requests/App/ManufacturerRequest.php routes/web.php tests/Feature/App/ManufacturerControllerTest.php
git commit -m "feat(manufacturers): Inertia controller, routes, validation"
```

---

### Task 9: Manufacturers frontend (index + create/edit pages)

**Files:**
- Create: `resources/js/pages/manufacturers/index.tsx`, `resources/js/pages/manufacturers/create.tsx`, `resources/js/pages/manufacturers/edit.tsx`, `resources/js/pages/manufacturers/manufacturer-form.tsx`

**Interfaces:**
- Consumes: `AppLayout`, `DataTable`, `TextField`, `@/components/ui/*`, Inertia `useForm`/`router`/`Link`; props shape from Task 8 (`manufacturers.data`, `manufacturers.meta`, `manufacturer`).
- Produces: the three route pages + a shared `ManufacturerForm` (props: `{ initial?: { id: string; name: string }; submitUrl: string; method: 'post' | 'put' }`).

- [ ] **Step 1: Create the shared form**

```tsx
// resources/js/pages/manufacturers/manufacturer-form.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';

interface Props {
    initial?: { id: string; name: string };
    submitUrl: string;
    method: 'post' | 'put';
}

export function ManufacturerForm({ initial, submitUrl, method }: Props) {
    const form = useForm({ name: initial?.name ?? '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(method, submitUrl);
    };

    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <TextField id="name" label="Name" required autoFocus
                value={form.data.name} onChange={(v) => form.setData('name', v)}
                error={form.errors.name} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
```

- [ ] **Step 2: Create the create/edit pages**

```tsx
// resources/js/pages/manufacturers/create.tsx
import AppLayout from '@/layouts/app-layout';
import { ManufacturerForm } from './manufacturer-form';

export default function CreateManufacturer() {
    return (
        <AppLayout title="New manufacturer" breadcrumbs={[{ label: 'Manufacturers', href: '/app/manufacturers' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New manufacturer</h1>
            <ManufacturerForm submitUrl="/app/manufacturers" method="post" />
        </AppLayout>
    );
}
```

```tsx
// resources/js/pages/manufacturers/edit.tsx
import AppLayout from '@/layouts/app-layout';
import { ManufacturerForm } from './manufacturer-form';

export default function EditManufacturer({ manufacturer }: { manufacturer: { id: string; name: string } }) {
    return (
        <AppLayout title="Edit manufacturer" breadcrumbs={[{ label: 'Manufacturers', href: '/app/manufacturers' }, { label: manufacturer.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit manufacturer</h1>
            <ManufacturerForm initial={manufacturer} submitUrl={`/app/manufacturers/${manufacturer.id}`} method="put" />
        </AppLayout>
    );
}
```

- [ ] **Step 3: Create the index page**

```tsx
// resources/js/pages/manufacturers/index.tsx
import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; models_count: number; assets_count: number; }

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'models_count', header: 'Models', cell: ({ row }) => <Badge variant="secondary">{row.original.models_count}</Badge> },
    { accessorKey: 'assets_count', header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/manufacturers/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => {
                    if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/manufacturers/${row.original.id}`);
                }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function ManufacturersIndex({ manufacturers }: { manufacturers: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Manufacturers" breadcrumbs={[{ label: 'Manufacturers' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Manufacturers</h1>
                <Button asChild><Link href="/app/manufacturers/create">New manufacturer</Link></Button>
            </div>
            <DataTable columns={columns} rows={manufacturers.data} pagination={manufacturers.meta} baseUrl="/app/manufacturers" />
        </AppLayout>
    );
}
```

- [ ] **Step 4: Build and verify types**

Run: `ddev exec pnpm run build`
Expected: build succeeds, no type errors.

- [ ] **Step 5: Manually verify the full CRUD**

Log in (via `/app-old` for now — Task 10 adds the Inertia login), then:
- `/app/manufacturers` → table renders with name/models/assets columns; search filters; paging works.
- "New manufacturer" → create; empty name shows the validation error under the field; success toast on save.
- Edit → name pre-filled, update persists.
- Delete → row removed after confirm.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/manufacturers
git commit -m "feat(manufacturers): Inertia index + create/edit pages"
```

---

### Task 10: Shared session auth — Inertia login, logout, Entra button, guard `/app`

**Files:**
- Create: `app/Http/Controllers/App/AuthController.php`, `resources/js/pages/auth/login.tsx`
- Modify: `routes/web.php` (login/logout routes + wrap `/app` resource routes in `auth`), `bootstrap/app.php` (redirect unauthenticated `/app/*` to `/app/login`)
- Test: `tests/Feature/App/AuthTest.php`; update `tests/Feature/App/ManufacturerControllerTest.php` if needed (already uses `actingAs`)

**Interfaces:**
- Consumes: web guard, `App\Models\User` (`login_enabled`), existing `MicrosoftAuthController` routes, `config('services.microsoft-azure.enabled')`.
- Produces: routes `app.login` (GET → Inertia `auth/login`), `app.login.attempt` (POST), `app.logout` (POST); `/app` resource + dashboard routes behind `auth`; login honors `login_enabled`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/App/AuthTest.php

use App\Models\User;

it('shows the inertia login page', function () {
    $this->get('/app/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

it('logs in a user with valid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password'), 'login_enabled' => true]);

    $this->post('/app/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect('/app');

    $this->assertAuthenticatedAs($user);
});

it('rejects a disabled account', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password'), 'login_enabled' => false]);

    $this->post('/app/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects wrong credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password'), 'login_enabled' => true]);

    $this->post('/app/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects unauthenticated visitors from /app to /app/login', function () {
    $this->get('/app')->assertRedirect('/app/login');
});

it('logs out', function () {
    $user = User::factory()->create(['login_enabled' => true]);
    $this->actingAs($user)->post('/app/logout')->assertRedirect('/app/login');
    $this->assertGuest();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AuthTest.php`
Expected: FAIL — routes/pages not defined.

- [ ] **Step 3: Create the auth controller**

```php
<?php // app/Http/Controllers/App/AuthController.php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/login', [
            'entraEnabled' => (bool) config('services.microsoft-azure.enabled'),
        ]);
    }

    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        if (! $request->user()->login_enabled) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages(['email' => __('This account is disabled.')]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/app');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('app.login');
    }
}
```

- [ ] **Step 4: Wire routes and the `/app` guard**

Rewrite the `/app` block in `routes/web.php`:

```php
use App\Http\Controllers\App\AuthController;
use App\Http\Controllers\App\ManufacturerController;

Route::prefix('app')->name('app.')->group(function () {
    // Guest
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'show'])->name('login');
        Route::post('login', [AuthController::class, 'attempt'])->name('login.attempt');
    });

    // Authenticated
    Route::middleware(['auth', \App\Http\Middleware\ApplyRuntimeSettings::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', fn () => \Inertia\Inertia::render('dashboard'))->name('dashboard');
        Route::resource('manufacturers', ManufacturerController::class)->except('show');
    });
});
```

- [ ] **Step 5: Redirect unauthenticated `/app/*` to the Inertia login**

In `bootstrap/app.php`, replace the single `redirectGuestsTo` with a request-aware closure so `/app/*` goes to the new login while everything else keeps going to Filament:

```php
$middleware->redirectGuestsTo(function ($request) {
    return $request->is('app', 'app/*')
        ? route('app.login')
        : route('filament.app.auth.login');
});
```

- [ ] **Step 6: Create the login page**

```tsx
// resources/js/pages/auth/login.tsx
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TextField } from '@/components/form/text-field';

export default function Login({ entraEnabled }: { entraEnabled: boolean }) {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/login');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-4">
            <Head title="Sign in" />
            <Card className="w-full max-w-sm">
                <CardHeader><CardTitle>Sign in to Inventorix</CardTitle></CardHeader>
                <CardContent className="space-y-6">
                    <form onSubmit={submit} className="space-y-4">
                        <TextField id="email" label="Email" value={form.data.email}
                            onChange={(v) => form.setData('email', v)} error={form.errors.email} autoFocus required />
                        <div className="space-y-2">
                            <label htmlFor="password" className="text-sm font-medium">Password</label>
                            <input id="password" type="password" className="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                                value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                        </div>
                        <Button type="submit" className="w-full" disabled={form.processing}>Sign in</Button>
                    </form>
                    {entraEnabled && (
                        <a href="/auth/microsoft/redirect"
                            className="flex w-full items-center justify-center rounded-md border py-2 text-sm hover:bg-accent">
                            Sign in with Microsoft
                        </a>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
```

- [ ] **Step 7: Run all new tests**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App`
Expected: PASS (Auth + Manufacturer suites green).

- [ ] **Step 8: Build and manually verify end-to-end**

Run: `ddev exec pnpm run build`
- Visit `/app` while logged out → redirected to `/app/login`.
- Sign in → land on `/app` dashboard; user menu shows email; log out returns to login.
- If Entra is enabled in config, the "Sign in with Microsoft" button appears and starts the existing flow.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/App/AuthController.php resources/js/pages/auth routes/web.php bootstrap/app.php tests/Feature/App/AuthTest.php
git commit -m "feat(auth): Inertia login/logout + Entra button, guard /app"
```

---

### Task 11: Final verification pass

**Files:** none (verification only).

- [ ] **Step 1: Run the full test suite**

Run: `ddev exec ./vendor/bin/phpunit`
Expected: all green (new + pre-existing Filament tests unaffected).

- [ ] **Step 2: Run the JS test suite**

Run: `ddev exec pnpm run test`
Expected: all green.

- [ ] **Step 3: Production build**

Run: `ddev exec pnpm run build`
Expected: succeeds; both Filament and Inertia bundles emitted.

- [ ] **Step 4: Manual coexistence check**

- `/app-old` → Filament panel fully functional and visually unchanged (styles not leaked).
- `/app` → Inertia app: login, dashboard, Manufacturers CRUD, light/dark toggle all working.
- `/` → still redirects to the Filament dashboard (unchanged; flips at cutover in Spec 9).

- [ ] **Step 5: Update the changelog / notes if the repo convention requires it**

Check `CHANGELOG.md` conventions; add an entry describing the new `/app` Inertia foundation if that matches the existing release-notes workflow. (Skip if releases are automated.)

- [ ] **Step 6: Commit any remaining docs**

```bash
git add -A
git commit -m "chore(inertia): finalize foundation spec 1 verification"
```

---

## Self-Review Notes

- **Spec coverage:** §1 coexistence → Task 1; §2 stack graft → Tasks 2–3; §3 shell/design system → Tasks 3–4; §4 DataTable/form kit/conventions → Tasks 5–7; §5 Manufacturers → Tasks 8–9; §6 auth → Task 10; §7 testing → embedded per task + Task 11. All sections covered.
- **Deviations from spec (intentional, noted):** (a) no `ManufacturerPolicy` exists, so authorization is `auth`-middleware only; (b) the Filament form's required `models` multiselect is dropped — the Inertia form edits `name` only, counts shown read-only in the table.
- **Type consistency:** `PaginationMeta` (Task 6) matches the `meta` shape returned by the controller (Task 8) and consumed by the index page (Task 9). `PageProps`/`User` (Task 4) match the middleware share (Task 2). `TableQuery` API (Task 5) matches its controller usage (Task 8). `ManufacturerForm` method/submitUrl props (Task 9) match the routes (Task 8).
- **MFA:** deferred per the spec — not implemented; Filament MFA remains on `/app-old`.

## Deferred from Spec 1 (DataTable)

The design spec (§4/§5) describes a fuller `DataTable` than what Spec 1 actually builds. The following features were intentionally **not** implemented in this pass and should be picked up when a resource first needs them:

- **Per-column filters** — filtering is currently limited to the single free-text `search` param across the `searchable` columns; there is no per-column filter UI or query param.
- **Row-selection checkboxes** — no selection column or selection state exists on `DataTable` yet.
- **Bulk-actions toolbar** — no toolbar for acting on a selection (depends on row-selection above).
- **Bulk destroy** — no batch-delete endpoint or UI; `destroy` is single-row only (see `ManufacturerController::destroy`).

Column sorting (server-side via `TableQuery::sortable` + the `nextSort`/clickable-header wiring) *is* implemented as of this fix pass.
