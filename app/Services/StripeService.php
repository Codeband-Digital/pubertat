<?php

namespace App\Services;

use Stripe\StripeClient;

/**
 * Сервис взаимодействия с API платежной системы Stripe.
 *
 * Используется для работы с платежной системой Stripe, например для получения ссылки на оплату.
 */
class StripeService
{
    /**
     * Получение ссылки на оплату.
     *
     * @param $transactionId
     * @param $priceId
     * @return string|null
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function getPaymentUrl($transactionId, $priceId): ?string
    {
        $stripeKey = config('payments.stripe.api_key');

        $stripe = new StripeClient($stripeKey);

        $stripeSession = $stripe->checkout->sessions->create([
            'success_url' => 'https://api.pbrtt.ru/stripeSuccess?session_id={CHECKOUT_SESSION_ID}&inv_id=' . $transactionId,
            'cancel_url'  => 'https://api.pbrtt.ru/fail',
            'line_items'  => [
                [
                    'price'    => $priceId,
                    'quantity' => 1,
                ],
            ],
            'mode'        => 'payment',
        ]);

        return $stripeSession->url;
    }
}
