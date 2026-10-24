<?php

namespace App\Http\Requests;

use App\Actions\Todos\TodoWorkflow;
use Illuminate\Foundation\Http\FormRequest;

class TodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return TodoWorkflow::rules();
    }
}
