<?php

namespace App\Support;

class AccessMatrix
{
    public const NONE = 'none';
    public const READ = 'read';
    public const EDIT = 'edit';

    public static function modules(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'assistant' => 'Inventory Assistant',
            'anomalies' => 'Stock Anomaly Agent',
            'categories' => 'Categories',
            'locations' => 'Locations',
            'assets' => 'Stock Items',
            'it_assets' => 'IT Asset Register',
            'movements' => 'Stock Movements',
            'ledger' => 'Monthly Ledger',
            'cogs' => 'COG Control',
            'settings' => 'Settings',
        ];
    }

    public static function roleOptions(): array
    {
        return [
            'admin' => 'Administrator',
            'it' => 'IT & Digital',
            'miri' => 'Miri Inventory',
            'supervisor' => 'Supervisor',
            'technician' => 'Technician',
            'none' => 'No access',
        ];
    }

    public static function levelOptions(): array
    {
        return [
            self::NONE => 'No Access',
            self::READ => 'Read Only',
            self::EDIT => 'Edit',
        ];
    }

    public static function permissionsForRole(string $role): array
    {
        $allEdit = array_fill_keys(array_keys(self::modules()), self::EDIT);

        return match ($role) {
            'admin', 'it', 'supervisor' => $allEdit,
            'miri' => [
                'dashboard' => self::EDIT, 'assistant' => self::READ, 'anomalies' => self::READ,
                'categories' => self::EDIT, 'locations' => self::EDIT, 'assets' => self::EDIT,
                'it_assets' => self::NONE, 'movements' => self::EDIT, 'ledger' => self::READ,
                'cogs' => self::EDIT, 'settings' => self::NONE,
            ],
            'technician' => [
                'dashboard' => self::READ, 'assistant' => self::NONE, 'anomalies' => self::NONE,
                'categories' => self::NONE, 'locations' => self::NONE, 'assets' => self::NONE,
                'it_assets' => self::EDIT, 'movements' => self::NONE, 'ledger' => self::NONE,
                'cogs' => self::NONE, 'settings' => self::NONE,
            ],
            default => array_fill_keys(array_keys(self::modules()), self::NONE),
        };
    }

    public static function normalizePermissions(?array $permissions, ?string $role = null): array
    {
        $base = self::permissionsForRole($role ?: 'none');
        $validLevels = array_keys(self::levelOptions());

        foreach (self::modules() as $key => $label) {
            $value = $permissions[$key] ?? $base[$key] ?? self::NONE;
            $base[$key] = in_array($value, $validLevels, true) ? $value : self::NONE;
        }

        return $base;
    }

    public static function permissionsForKlStaff(): array
    {
        return self::permissionsForRole('none');
    }
}
