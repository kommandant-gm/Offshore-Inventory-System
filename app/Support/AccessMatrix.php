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
            'supervisor' => 'Supervisor',
            'viewer' => 'Viewer',
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
            'admin' => $allEdit,
            'supervisor' => [
                'dashboard' => self::READ,
                'assistant' => self::READ,
                'anomalies' => self::READ,
                'categories' => self::EDIT,
                'locations' => self::EDIT,
                'assets' => self::EDIT,
                'movements' => self::EDIT,
                'ledger' => self::READ,
                'cogs' => self::EDIT,
                'settings' => self::READ,
            ],
            default => [
                'dashboard' => self::READ,
                'assistant' => self::READ,
                'anomalies' => self::READ,
                'categories' => self::READ,
                'locations' => self::READ,
                'assets' => self::READ,
                'movements' => self::READ,
                'ledger' => self::READ,
                'cogs' => self::READ,
                'settings' => self::NONE,
            ],
        };
    }

    public static function normalizePermissions(?array $permissions, ?string $role = null): array
    {
        $base = self::permissionsForRole($role ?: 'viewer');
        $validLevels = array_keys(self::levelOptions());

        foreach (self::modules() as $key => $label) {
            $value = $permissions[$key] ?? $base[$key] ?? self::NONE;
            $base[$key] = in_array($value, $validLevels, true) ? $value : self::NONE;
        }

        return $base;
    }
}
