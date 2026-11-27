<?php

namespace App\Enums;

/**
 * The permission catalogue. Values are what groups and users store in their
 * permission maps and what gates and middleware check; the order of the cases
 * is the order of the permission matrix in the UI.
 */
enum Permission: string
{
    case SUPERUSER = 'superuser';
    case ADMIN = 'admin';
    case IMPORT = 'import';
    case REPORTS_VIEW = 'reports.view';
    case ASSETS_VIEW = 'assets.view';
    case ASSETS_CREATE = 'assets.create';
    case ASSETS_EDIT = 'assets.edit';
    case ASSETS_DELETE = 'assets.delete';
    case ASSETS_CHECKOUT = 'assets.checkout';
    case ASSETS_CHECKIN = 'assets.checkin';
    case ASSETS_FILES = 'assets.files';
    case INCIDENTS_VIEW = 'incidents.view';
    case INCIDENTS_CREATE = 'incidents.create';
    case INCIDENTS_EDIT = 'incidents.edit';
    case INCIDENTS_DELETE = 'incidents.delete';
    case HANDOVERS_VIEW = 'handovers.view';
    case HANDOVERS_CREATE = 'handovers.create';
    case ACCESSORIES_VIEW = 'accessories.view';
    case ACCESSORIES_CREATE = 'accessories.create';
    case ACCESSORIES_EDIT = 'accessories.edit';
    case ACCESSORIES_DELETE = 'accessories.delete';
    case MODELS_VIEW = 'models.view';
    case MODELS_CREATE = 'models.create';
    case MODELS_EDIT = 'models.edit';
    case MODELS_DELETE = 'models.delete';
    case CATEGORIES_VIEW = 'categories.view';
    case CATEGORIES_CREATE = 'categories.create';
    case CATEGORIES_EDIT = 'categories.edit';
    case CATEGORIES_DELETE = 'categories.delete';
    case MANUFACTURERS_VIEW = 'manufacturers.view';
    case MANUFACTURERS_CREATE = 'manufacturers.create';
    case MANUFACTURERS_EDIT = 'manufacturers.edit';
    case MANUFACTURERS_DELETE = 'manufacturers.delete';
    case SUPPLIERS_VIEW = 'suppliers.view';
    case SUPPLIERS_CREATE = 'suppliers.create';
    case SUPPLIERS_EDIT = 'suppliers.edit';
    case SUPPLIERS_DELETE = 'suppliers.delete';
    case LOCATIONS_VIEW = 'locations.view';
    case LOCATIONS_CREATE = 'locations.create';
    case LOCATIONS_EDIT = 'locations.edit';
    case LOCATIONS_DELETE = 'locations.delete';
    case STATUSLABELS_VIEW = 'statuslabels.view';
    case STATUSLABELS_CREATE = 'statuslabels.create';
    case STATUSLABELS_EDIT = 'statuslabels.edit';
    case STATUSLABELS_DELETE = 'statuslabels.delete';
    case CUSTOMFIELDS_VIEW = 'customfields.view';
    case CUSTOMFIELDS_CREATE = 'customfields.create';
    case CUSTOMFIELDS_EDIT = 'customfields.edit';
    case CUSTOMFIELDS_DELETE = 'customfields.delete';
    case DEPRECIATIONS_VIEW = 'depreciations.view';
    case DEPRECIATIONS_CREATE = 'depreciations.create';
    case DEPRECIATIONS_EDIT = 'depreciations.edit';
    case DEPRECIATIONS_DELETE = 'depreciations.delete';
    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_EDIT = 'users.edit';
    case USERS_DELETE = 'users.delete';

    /** Section key, the part before the dot ('global' for the unscoped ones). */
    public function section(): string
    {
        return match ($this) {
            self::SUPERUSER, self::ADMIN, self::IMPORT, self::REPORTS_VIEW => 'global',
            default => explode('.', $this->value)[0],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPERUSER => 'Superuser',
            self::ADMIN => 'Admin',
            self::IMPORT => 'Import',
            self::REPORTS_VIEW => 'View reports',
            self::ASSETS_CHECKOUT => 'Check out',
            self::ASSETS_CHECKIN => 'Check in',
            self::ASSETS_FILES => 'Manage files',
            default => ucfirst(explode('.', $this->value)[1]),
        };
    }

    /** Only a superuser may hold or hand out these; admin does not imply them. */
    public function isSuperuserOnly(): bool
    {
        return $this === self::SUPERUSER || $this === self::ADMIN;
    }

    /** @return array<string, string> section key => heading, in matrix order */
    public static function sectionLabels(): array
    {
        return [
            'global' => 'Global',
            'assets' => 'Assets',
            'incidents' => 'Incidents',
            'handovers' => 'Handovers',
            'accessories' => 'Accessories',
            'models' => 'Asset models',
            'categories' => 'Categories',
            'manufacturers' => 'Manufacturers',
            'suppliers' => 'Suppliers',
            'locations' => 'Locations',
            'statuslabels' => 'Status labels',
            'customfields' => 'Custom fields',
            'depreciations' => 'Depreciations',
            'users' => 'Users',
        ];
    }

    /** @return list<array{section: string, label: string, permissions: list<array{value: string, label: string}>}> */
    public static function catalogue(): array
    {
        $catalogue = [];
        foreach (self::sectionLabels() as $section => $label) {
            $catalogue[] = [
                'section' => $section,
                'label' => $label,
                'permissions' => array_values(array_map(
                    fn (self $p) => ['value' => $p->value, 'label' => $p->label()],
                    array_filter(self::cases(), fn (self $p) => $p->section() === $section),
                )),
            ];
        }

        return $catalogue;
    }
}
