<?php

namespace App\Support;

class Permissions
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (config('permissions.sections') as $section) {
            foreach (array_keys($section['permissions']) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (config('permissions.sections') as $key => $section) {
            $permissions = [];
            foreach ($section['permissions'] as $name => $label) {
                $permissions[] = ['name' => $name, 'label' => $label];
            }
            $groups[] = ['key' => $key, 'label' => $section['label'], 'permissions' => $permissions];
        }

        return $groups;
    }

    public static function superAdminRole(): string
    {
        return config('permissions.super_admin_role');
    }
}
