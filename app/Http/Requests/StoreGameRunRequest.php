<?php

namespace App\Http\Requests;

use App\Models\GameRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'player_name' => ['nullable', 'string', 'max:24'],
            'outcome' => ['required', Rule::in([GameRun::OUTCOME_COMPLETED, GameRun::OUTCOME_CAUGHT])],
            'time_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('player_name', ''));
        $this->merge(['player_name' => $name === '' ? 'Anonymous' : $name]);
    }
}
