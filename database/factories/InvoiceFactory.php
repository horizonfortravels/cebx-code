<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'account_id'     => Account::factory(),
            'invoice_number' => 'INV-' . now()->format('Ym') . '-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'type'           => Invoice::TYPE_INVOICE,
            'subtotal'       => 100.00,
            'tax_amount'     => 15.00,
            'discount_amount' => 0,
            'total'          => 115.00,
            'currency'       => 'SAR',
            'tax_rate'       => 15.00,
            'status'         => Invoice::STATUS_PAID,
            'issued_at'      => now(),
            'paid_at'        => now(),
        ];
    }
}
