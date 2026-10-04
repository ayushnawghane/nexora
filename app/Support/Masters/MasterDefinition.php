<?php

namespace App\Support\Masters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Describes one config-driven master (see config/masters.php). */
class MasterDefinition
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(public readonly string $key, private readonly array $config)
    {
        if (! is_subclass_of($config['model'] ?? null, Model::class)) {
            throw new InvalidArgumentException("Master [{$key}] has no valid model.");
        }
    }

    public function label(): string
    {
        return $this->config['label'];
    }

    public function singular(): string
    {
        return $this->config['singular'] ?? Str::singular(Str::lower($this->config['label']));
    }

    public function group(): string
    {
        return $this->config['group'];
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return $this->config['model'];
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        $class = $this->modelClass();

        return $class::query()->with($this->relations());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return $this->config['fields'];
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return array_values(array_filter(array_map(fn (array $f) => $f['relation'] ?? null, $this->fields())));
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return array_keys(array_filter($this->fields(), fn (array $f) => ($f['search'] ?? false) && ! isset($f['relation'])));
    }

    /**
     * @return list<string>
     */
    public function sortable(): array
    {
        return array_keys(array_filter($this->fields(), fn (array $f) => ($f['sortable'] ?? false) && ! isset($f['relation'])));
    }

    public function defaultSort(): string
    {
        return $this->config['default_sort'] ?? ($this->sortable()[0] ?? 'id');
    }

    /**
     * @return list<string>
     */
    public function dependents(): array
    {
        return $this->config['dependents'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalise(array $input): array
    {
        foreach ($this->fields() as $attribute => $field) {
            if (! array_key_exists($attribute, $input)) {
                continue;
            }
            $value = $input[$attribute];
            if (($field['type'] ?? null) === 'boolean') {
                $input[$attribute] = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value;

                continue;
            }
            if (is_string($value)) {
                $value = trim($value);
                $value = match ($field['transform'] ?? null) {
                    'upper' => Str::upper($value),
                    'lower' => Str::lower($value),
                    default => $value,
                };
                $value = $value === '' ? null : $value;
            }
            $input[$attribute] = $value;
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input  the (normalised) submitted data, for multi-column uniqueness
     * @return array<string, mixed>
     */
    public function rules(?Model $record = null, array $input = []): array
    {
        $model = new ($this->modelClass());
        $table = $model->getTable();
        $rules = [];

        foreach ($this->fields() as $attribute => $field) {
            $fieldRules = $field['rules'] ?? [];

            if ($field['unique'] ?? false) {
                $fieldRules[] = Rule::unique($table, $attribute)->ignore($record?->getKey());
            }

            if (($field['type'] ?? null) === 'select') {
                $related = new ($field['options'][0]);
                $exists = Rule::exists($related->getTable(), $related->getKeyName());
                if (in_array(SoftDeletes::class, class_uses_recursive($related), true)) {
                    $exists->whereNull('deleted_at');
                }
                $fieldRules[] = $exists;
            }

            if (($field['type'] ?? null) === 'enum') {
                $fieldRules[] = Rule::enum($field['enum']);
            }

            if (($field['type'] ?? null) === 'boolean') {
                $fieldRules = ['required', 'boolean'];
            }

            if (($field['type'] ?? null) === 'multiselect') {
                $related = new ($field['options'][0]);
                $rules["{$attribute}.*"] = ['integer', 'distinct', Rule::exists($related->getTable(), $related->getKeyName())];
            }

            $rules[$attribute] = $fieldRules;
        }

        foreach ($this->config['unique_together'] ?? [] as $columns) {
            [$first, $second] = $columns;
            $rules[$first][] = Rule::unique($table, $first)
                ->where(fn ($q) => $q->where($second, $input[$second] ?? null))
                ->ignore($record?->getKey());
        }

        return $rules;
    }

    /**
     * Attribute labels for validation messages.
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return array_map(fn (array $f) => Str::lower($f['label']), $this->fields());
    }

    /**
     * Saves validated data: scalar/belongsTo columns via fill, belongsToMany via sync.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(Model $record, array $data): Model
    {
        $columns = [];
        $syncs = [];

        foreach ($this->fields() as $attribute => $field) {
            if (! array_key_exists($attribute, $data)) {
                continue;
            }
            if (($field['type'] ?? null) === 'multiselect') {
                $syncs[$field['relation']] = $data[$attribute] ?? [];
            } else {
                $columns[$attribute] = $data[$attribute];
            }
        }

        $record->fill($columns)->save();

        foreach ($syncs as $relation => $ids) {
            $record->{$relation}()->sync($ids);
        }

        return $record;
    }

    /**
     * Row for the list table.
     *
     * @return array<string, mixed>
     */
    public function toRow(Model $record): array
    {
        $row = ['id' => $record->getKey(), 'is_active' => (bool) $record->getAttribute('is_active')];

        foreach ($this->fields() as $attribute => $field) {
            $row[$attribute] = match ($field['type'] ?? 'text') {
                'select' => $record->getAttribute($attribute),
                'multiselect' => $record->{$field['relation']}->modelKeys(),
                'enum' => $record->getAttribute($attribute)?->value,
                'boolean' => (bool) $record->getAttribute($attribute),
                default => $record->getAttribute($attribute),
            };

            if (($field['type'] ?? null) === 'enum') {
                $row["{$attribute}__label"] = $record->getAttribute($attribute)?->label() ?? ($field['empty_label'] ?? null);
            }

            if (isset($field['relation'])) {
                $labelColumn = $field['options'][1];
                $related = $record->{$field['relation']};
                $row["{$attribute}__label"] = ($field['type'] ?? null) === 'multiselect'
                    ? $related->pluck($labelColumn)->implode(', ')
                    : $related?->getAttribute($labelColumn);
            }
        }

        return $row;
    }

    /**
     * Field definitions for the frontend form, including select options.
     *
     * @return list<array<string, mixed>>
     */
    public function formSchema(): array
    {
        $schema = [];

        foreach ($this->fields() as $attribute => $field) {
            $entry = [
                'name' => $attribute,
                'label' => $field['label'],
                // Enum fields are plain selects on the page.
                'type' => ($field['type'] ?? 'text') === 'enum' ? 'select' : ($field['type'] ?? 'text'),
                'required' => ($field['type'] ?? null) !== 'boolean' && in_array('required', $field['rules'] ?? [], true),
                'list' => (bool) ($field['list'] ?? false),
                'sortable' => (bool) ($field['sortable'] ?? false),
                'hint' => $field['hint'] ?? null,
            ];

            if (($field['type'] ?? null) === 'enum') {
                $entry['options'] = $field['enum']::options();
            }

            if (isset($field['options'])) {
                [$class, $labelColumn] = $field['options'];
                $query = $class::query();
                if (method_exists(new $class, 'scopeActive')) {
                    $query->active();
                }
                $entry['options'] = $query->orderBy($labelColumn)->get([(new $class)->getKeyName(), $labelColumn])
                    ->map(fn (Model $m) => ['value' => $m->getKey(), 'label' => $m->getAttribute($labelColumn)])
                    ->values();
            }

            $schema[] = $entry;
        }

        return $schema;
    }
}
