<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportStoneGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'excel_file.required' => 'Excel file is required',
            'excel_file.file' => 'Must be a valid file',
            'excel_file.mimes' => 'File must be Excel or CSV format',
            'excel_file.max' => 'File size must not exceed 10MB',
        ];
    }
}