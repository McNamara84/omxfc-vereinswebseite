<?php

namespace App\Http\Requests;

use App\Enums\AudiobookEpisodeStatus;
use App\Rules\ValidReleaseTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AudiobookEpisodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $episodeId = $this->route('episode')?->id;

        return [
            'episode_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('audiobook_episodes', 'episode_number')->ignore($episodeId),
            ],
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'planned_release_date' => ['required', 'string', new ValidReleaseTime],
            'status' => 'required|in:' . implode(',', AudiobookEpisodeStatus::values()),
            'responsible_user_id' => 'nullable|exists:users,id',
            'progress' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
            'roles' => 'array',
            'roles.*.name' => 'required|string|max:255',
            'roles.*.description' => 'nullable|string|max:1000',
            'roles.*.takes' => 'required|integer|min:0',
            'roles.*.member_id' => 'nullable|exists:users,id',
            'roles.*.member_name' => 'nullable|string|max:255',
            'roles.*.contact_email' => 'nullable|email:rfc|max:255',
            'roles.*.speaker_pseudonym' => 'nullable|string|max:255',
            'roles.*.uploaded' => 'nullable|boolean',
        ];
    }
}
