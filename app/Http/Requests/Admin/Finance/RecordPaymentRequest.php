<?php

namespace App\Http\Requests\Admin\Finance;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Person;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('record', Payment::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payer_person_id' => ['required', 'integer', Rule::exists(Person::class, 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.bill_item_id' => ['required', 'integer', 'distinct'],
            'items.*.amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::selectable())],
            'note' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payer_person_id' => 'Pembayar',
            'items' => 'Rincian yang dibayar',
            'items.*.bill_item_id' => 'Rincian',
            'items.*.amount' => 'Nominal',
            'paid_on' => 'Tanggal bayar',
            'method' => 'Cara bayar',
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
