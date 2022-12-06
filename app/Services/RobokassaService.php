<?php

namespace App\Services;

use Stripe\StripeClient;

/**
 * Сервис взаимодействия с API платежной системы Robokassa.
 *
 * Используется для работы с платежной системой Robokassa, например для получения ссылки на оплату.
 */
class RobokassaService
{
    private $merchantLogin;
    private $merchantPassword;
    private $merchantUrl;

    public function __construct()
    {
        $this->merchantLogin = config('payments.robokassa.login');
        $this->merchantPassword = config('payments.robokassa.testpass1');
        $this->merchantUrl = config('payments.robokassa.merchant_url');
    }

    /**
     * Получение ссылки на оплату.
     *
     * @param string $transactionId
     * @param string $price
     * @param string $paymentDescription
     * @return string
     */
    public function getPaymentUrl(string $transactionId, string $price, string $paymentDescription): string
    {
        $signature = md5("$this->merchantLogin:$price:$transactionId:$this->merchantPassword");

        return  "$this->merchantUrl?MerchantLogin=$this->merchantLogin&" .
            "OutSum=$price&InvId=$transactionId&Description=$paymentDescription&SignatureValue=$signature";
    }
}
