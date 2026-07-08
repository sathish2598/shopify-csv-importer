<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => [
                'required',
                'file',
                'extensions:csv,txt',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Please choose a CSV file to upload.',
            'csv_file.extensions' => 'The file must be a CSV file.',
            'csv_file.mimetypes' => 'The file must be a valid CSV file.',
            'csv_file.max' => 'The file may not be larger than 2 MB.',
        ];
    }
}
