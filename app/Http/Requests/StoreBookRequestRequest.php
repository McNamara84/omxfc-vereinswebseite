<?php

namespace App\Http\Requests;

use App\Support\Romantausch\InputRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return InputRules::single();
    }

    public function messages(): array
    {
        return InputRules::messages(true);
    }
}
