<?php

namespace App\Http\Requests\Portal;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->person_id !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.bill_item_id' => ['required', 'integer', 'distinct'],
            'items.*.amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::selectable())],
            'note' => ['nullable', 'string', 'max:500'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => 'Rincian yang dibayar',
            'items.*.bill_item_id' => 'Rincian',
            'items.*.amount' => 'Nominal',
            'paid_on' => 'Tanggal bayar',
            'method' => 'Cara bayar',
            'note' => 'Catatan',
            'proof' => 'Bukti pembayaran',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu rincian yang dibayar.',
            'items.min' => 'Pilih minimal satu rincian yang dibayar.',
            'items.*.bill_item_id.distinct' => 'Rincian yang sama tidak boleh dipilih dua kali.',
        ];
    }
}
