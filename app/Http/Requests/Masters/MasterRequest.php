<?php

namespace App\Http\Requests\Masters;

use App\Support\Masters\MasterDefinition;
use App\Support\Masters\MasterRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class MasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masters.manage');
    }

    public function definition(): MasterDefinition
    {
        return app(MasterRegistry::class)->get((string) $this->route('master'));
    }

    public function record(): ?Model
    {
        $id = $this->route('record');

        return $id === null ? null : $this->definition()->query()->findOrFail($id);
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->definition()->normalise($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->definition()->rules($this->record(), $this->all());
    }

    public function attributes(): array
    {
        return $this->definition()->attributeLabels();
    }
}
