<?php

namespace App\Concerns;

use App\Enums\DocumentVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait DocumentValidationRules
{
    public const string ALLOWED_MIME_TYPES = 'pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg';

    public const int MAX_FILE_KILOBYTES = 20 * 1024;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function documentFolderRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::enum(DocumentVisibility::class)],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function documentRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::enum(DocumentVisibility::class)],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function documentFileRules(bool $required = true): array
    {
        return [
            'file' => [
                $required ? 'required' : 'nullable',
                'file',
                'mimes:'.self::ALLOWED_MIME_TYPES,
                'max:'.self::MAX_FILE_KILOBYTES,
            ],
        ];
    }
}
