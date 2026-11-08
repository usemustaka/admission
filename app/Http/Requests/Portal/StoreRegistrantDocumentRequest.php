<?php

namespace App\Http\Requests\Portal;

use App\Enums\DocumentType;
use App\Models\RegistrantDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrantDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = DocumentType::tryFrom((string) $this->input('type'));

        return $type === null || $this->user()->can('manageDocument', [$this->route('registrant'), $type]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = DocumentType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'slot' => ['nullable', 'integer', 'min:1', 'max:'.($type?->maxFiles() ?? 1)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg', 'max:'.RegistrantDocument::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['type' => 'Jenis berkas', 'slot' => 'Urutan berkas', 'file' => 'Berkas'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Berkas harus berupa PDF atau JPG.',
            'file.max' => 'Ukuran berkas maksimal 1 MB.',
            'file.uploaded' => 'Berkas gagal diunggah. Pastikan ukurannya maksimal 1 MB.',
        ];
    }
}
