<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'game' => ['required', Rule::in(['cs2', 'cs16'])],
            'ip' => ['required', 'string', 'max:45', 'ip'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'max_players' => ['required', 'integer', 'between:0,65535'],
            'query_type' => ['required', 'string', 'max:50'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['enabled' => $this->boolean('enabled')]);
    }
}
