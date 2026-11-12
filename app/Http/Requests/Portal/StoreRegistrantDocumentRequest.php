<?php

namespace App\Http\Requests\Portal;

use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Support\DocumentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrantDocumentRequest extends FormRequest
{
    private function kind(): ?DocumentKind
    {
        $registrant = $this->route('registrant');

        return $registrant instanceof Registrant ? DocumentKind::resolve($registrant, (string) $this->input('type')) : null;
    }

    public function authorize(): bool
    {
        $kind = $this->kind();

        return $kind === null || $this->user()->can('manageDocument', [$this->route('registrant'), $kind]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kind = $this->kind();

        return [
            'type' => ['required', 'string', fn (string $attribute, mixed $value, \Closure $fail) => $kind === null ? $fail('Jenis berkas tidak dikenal.') : null],
            'slot' => ['nullable', 'integer', 'min:1', 'max:'.($kind === null ? 1 : $kind->maxFiles)],
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
