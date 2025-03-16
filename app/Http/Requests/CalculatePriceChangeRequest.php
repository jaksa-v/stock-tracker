<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CalculatePriceChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stocks' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];
    }

    /**
     * Get the validated stock symbols as an array.
     *
     * @return array<int, string>
     */
    public function getValidatedSymbols(): array
    {
        $stocksString = $this->validated()['stocks'];

        $symbols = explode(',', $stocksString);

        return collect($symbols)
            ->map(fn ($symbol) => trim(strtoupper($symbol)))
            ->filter(fn ($symbol) => ! empty($symbol))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Handle a failed validation attempt.
     *
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        throw new HttpResponseException(
            response()->json([
                'error' => 'Invalid input parameters',
                'message' => $errors->first(),
            ], 400)
        );
    }
}
