<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePolicyRequestRequest extends FormRequest
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
            'client_request_id' => [
                'required',
                'string',
                'max:100',
                // Unique with agent_id will be checked in controller
            ],
            'agent_id' => [
                'required',
                'string',
                'max:50',
            ],
            'customer_name' => [
                'required',
                'string',
                'max:255',
            ],
            'date_of_birth' => [
                'required',
                'date',
                'before:today',
            ],
            'destination' => [
                'required',
                Rule::in(['DOMESTIC', 'INTERNATIONAL']),
            ],
            'trip_start' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'trip_end' => [
                'required',
                'date',
                'after_or_equal:trip_start',
            ],
            'plan_code' => [
                'required',
                Rule::in(['BASIC', 'PLUS', 'PREMIUM']),
            ],
            'sum_insured' => [
                'required',
                'numeric',
                'min:1000',
                'max:10000000',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'client_request_id.required' => 'Client request ID is required.',
            'agent_id.required' => 'Agent ID is required.',
            'customer_name.required' => 'Customer name is required.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'destination.required' => 'Destination is required.',
            'destination.in' => 'Destination must be either DOMESTIC or INTERNATIONAL.',
            'trip_start.required' => 'Trip start date is required.',
            'trip_start.after_or_equal' => 'Trip start date must be today or in the future.',
            'trip_end.required' => 'Trip end date is required.',
            'trip_end.after_or_equal' => 'Trip end date must be on or after trip start date.',
            'plan_code.required' => 'Plan code is required.',
            'plan_code.in' => 'Plan code must be one of: BASIC, PLUS, PREMIUM.',
            'sum_insured.required' => 'Sum insured is required.',
            'sum_insured.numeric' => 'Sum insured must be a number.',
            'sum_insured.min' => 'Sum insured must be at least 1,000.',
            'sum_insured.max' => 'Sum insured cannot exceed 10,000,000.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize date formats
        if ($this->has('date_of_birth')) {
            $this->merge([
                'date_of_birth' => \Carbon\Carbon::parse($this->date_of_birth)->format('Y-m-d'),
            ]);
        }
        if ($this->has('trip_start')) {
            $this->merge([
                'trip_start' => \Carbon\Carbon::parse($this->trip_start)->format('Y-m-d'),
            ]);
        }
        if ($this->has('trip_end')) {
            $this->merge([
                'trip_end' => \Carbon\Carbon::parse($this->trip_end)->format('Y-m-d'),
            ]);
        }
    }
}