<?php

namespace App\Http\Requests;

use App\Support\Romantausch\InputRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreBundleOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return InputRules::bundle();
    }

    public function messages(): array
    {
        return InputRules::messages();
    }
}
