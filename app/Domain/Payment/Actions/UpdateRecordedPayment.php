<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\Models\Payment;
use App\Enums\Payments\PaymentMethod;
use App\Enums\Payments\PaymentProcessor;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class UpdateRecordedPayment
{
    /**
     * @param  array{
     *     payment_method_code?: string,
     *     processor?: string,
     *     reference_number?: ?string,
     *     memo?: ?string,
     *     paid_at?: ?string
     * }  $validated
     */
    public function __invoke(Payment $payment, array $validated): Payment
    {
        $data = [];

        if (array_key_exists('payment_method_code', $validated)) {
            $method = PaymentMethod::tryFrom((string) $validated['payment_method_code'])
                ?? throw ValidationException::withMessages([
                    'payment_method_code' => ['Invalid payment method.'],
                ]);
            $data['payment_method_code'] = $method->value;
        }

        if (array_key_exists('processor', $validated)) {
            $processor = PaymentProcessor::tryFrom((string) $validated['processor'])
                ?? throw ValidationException::withMessages([
                    'processor' => ['Invalid processor.'],
                ]);
            $data['processor'] = $processor->value;
        }

        if (array_key_exists('reference_number', $validated)) {
            $data['reference_number'] = $validated['reference_number'];
        }

        if (array_key_exists('memo', $validated)) {
            $data['memo'] = $validated['memo'];
        }

        if (array_key_exists('paid_at', $validated)) {
            $data['paid_at'] = $validated['paid_at'] !== null && $validated['paid_at'] !== ''
                ? Carbon::parse((string) $validated['paid_at'])
                : null;
        }

        if ($data !== []) {
            $payment->update($data);
        }

        return $payment->fresh();
    }
}
