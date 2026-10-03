<?php

namespace App\Support\Masters;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MasterRegistry
{
    /** @var array<string, MasterDefinition> */
    private array $resolved = [];

    public function get(string $key): MasterDefinition
    {
        $config = config("masters.definitions.{$key}");

        if (! is_array($config)) {
            throw new NotFoundHttpException("Unknown master [{$key}].");
        }

        return $this->resolved[$key] ??= new MasterDefinition($key, $config);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(config('masters.definitions'));
    }

    /**
     * @return list<array{key: string, label: string, masters: list<array{key: string, label: string}>}>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach (config('masters.groups') as $groupKey => $groupLabel) {
            $masters = [];
            foreach ($this->keys() as $key) {
                $definition = $this->get($key);
                if ($definition->group() === $groupKey) {
                    $masters[] = ['key' => $key, 'label' => $definition->label()];
                }
            }
            $groups[] = ['key' => $groupKey, 'label' => $groupLabel, 'masters' => $masters];
        }

        return $groups;
    }
}
