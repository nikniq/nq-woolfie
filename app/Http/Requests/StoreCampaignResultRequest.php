<?php

namespace App\Http\Requests;

use App\Models\CampaignResult;
use App\Models\GameRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign' => ['required', 'uuid'],
            'player_name' => ['nullable', 'string', 'max:24'],
            'difficulty' => ['nullable', Rule::in(GameRun::DIFFICULTIES)],
            'daily' => ['nullable', 'date_format:Y-m-d'],
            'outcome' => ['required', Rule::in([CampaignResult::OUTCOME_ESCAPED, CampaignResult::OUTCOME_FAILED])],
            'score' => ['required', 'integer', 'min:0', 'max:1000000'],
            'time_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
            'kills' => ['required', 'integer', 'min:0', 'max:1000'],
            'alarms' => ['required', 'integer', 'min:0', 'max:1000'],
            'lives_lost' => ['required', 'integer', 'min:0', 'max:1000'],
            'stones' => ['required', 'integer', 'min:0', 'max:1000'],
            'bodies_hidden' => ['integer', 'min:0', 'max:1000'],
            'rooms_cleared' => ['required', 'integer', 'min:0', 'max:100'],
            'rooms_total' => ['required', 'integer', 'min:1', 'max:100'],
            'all_gold' => ['required', 'boolean'],
            'all_prisoners' => ['required', 'boolean'],
            'disguised_at_end' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('player_name', ''));
        $this->merge([
            'player_name' => $name === '' ? 'Anonymous' : $name,
            'difficulty' => $this->input('difficulty') ?: 'normal',
            'bodies_hidden' => (int) $this->input('bodies_hidden', 0),
        ]);
    }
}
