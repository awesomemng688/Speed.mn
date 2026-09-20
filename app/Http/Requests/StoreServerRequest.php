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
            'category' => ['nullable', 'required_if:game,cs16', Rule::in(['public-1', 'public-2', 'knife-1', 'knife-2'])],
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
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'category' => $this->input('game') === 'cs16' ? $this->input('category') : null,
        ]);
    }
}
