<?php

namespace App\Services;

use App\Exceptions\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Слой для отправки запросов в API.
 *
 * Для использования необходимо расширить сервис для интеграции, классом BaseApiService,
 * а также заполнить необходимые параметры, далее использовать метод request.
 *
 * <?php
 *
 * class ExamplePaymentService extends BaseApiService
 * {
 *      public function __construct()
 *      {
 *          $this->host = 'https://some-api-host';
 *          $this->token = 'Bearer some-api-token';
 *          $this->tokenHeaderKey = 'Authorization';
 *      }
 *
 *      public function pay()
 *      {
 *          $this->request('/pay', 'POST', [
 *              'foo' => 'bar'
 *          ]);
 *      }
 * }
 */
abstract class BaseApiService
{
    protected $host;
    protected $timeout;
    protected $token;
    protected $tokenHeaderKey;
    protected $additionalHeaders;
    protected $errorDescriptionKey;
    protected $requestType = 'json';

    /**
     * Отправка запроса.
     *
     * @param $endpoint
     * @param $method
     * @param array $parameters
     * @return array
     * @throws GuzzleException
     * @throws ApiException
     */
    protected function request($endpoint, $method, $parameters = []): array
    {
        $client = new Client();
        $headers = [];

        $headers[$this->tokenHeaderKey] = $this->token;

        if (!empty($this->additionalHeaders)) {
            $headers = array_merge($headers, $this->additionalHeaders);
        }

        $body = ['headers' => $headers];

        if ($this->requestType === 'json') {
            $body['json'] = $parameters;
        } else {
            $body['form_params'] = $parameters;
        }

        if (!empty($this->timeout)) {
            $body['timeout'] = $this->timeout;
            $body['connect_timeout'] = $this->timeout;
        }

        try {
            $response = $client->request($method, $this->host . $endpoint, $body);
            $output['code'] = $response->getStatusCode();
            $output['data'] = json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $exception) {
            $error = json_decode($exception->getResponse()->getBody()->getContents(), true);

            if ($exception->getCode() !== 401) {
                $errorMessage = $this->errorDescriptionKey ? $error[$this->errorDescriptionKey] : 'Error in request';
                throw new ApiException($errorMessage, $exception->getCode());
            }

            $output['code'] = 401;
            $output['data'] = ['error' => $error];
        }

        return $output;
    }
}
