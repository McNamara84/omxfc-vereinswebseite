<?php

namespace App\Http\Requests;

use App\Support\Romantausch\InputRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($offer = $this->route('offer')) && $this->user()->can('update', $offer);
    }

    public function rules(): array
    {
        return InputRules::single(true);
    }

    public function messages(): array
    {
        return InputRules::messages();
    }
}
