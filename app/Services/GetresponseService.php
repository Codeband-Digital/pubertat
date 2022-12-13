<?php

namespace App\Services;

/**
 * Сервис взаимодействия с API Getresponse.
 *
 * Используется для работы в сервисе рассылок getresponse.com, например, для работы с контактами.
 */
class GetresponseService extends BaseApiService
{
    const CASE_1_LEAD_CAMPAIGN_ID = 'oPIZt';
    const CASE_1_SOLD_CAMPAIGN_ID = 'oPIWi';

    public function __construct()
    {
        $this->host = config('getresponse.api_url');
        $this->token = 'api-key ' . config('getresponse.api_key');
        $this->tokenHeaderKey = 'X-Auth-Token';
    }

    /**
     * Создание контакта.
     *
     * @param string $email Адрес электронной почты.
     * @param string $campaignId Id компании (списка).
     * @throws \App\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createContact(string $email, string $campaignId)
    {
        $this->request(
            '/contacts',
            'POST',
            ['email' => $email, 'campaign' => ['campaignId' => $campaignId]]
        );
    }

    /**
     * Получение контакта по e-mail и компании.
     *
     * @param string $email Адрес электронной почты.
     * @param string $campaignId Id компании (списка).
     * @return array
     * @throws \App\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getContactByEmailFromCampaign(string $email, string $campaignId): array
    {
        return $this->request(
            "/campaigns/$campaignId/contacts?query[email]=$email",
            'GET'
        );
    }

    /**
     * Изменение компании у контакта.
     *
     * @param string $contactId Id контакта в getresponse.
     * @param string $email Адрес электронной почты.
     * @param string $campaignId Id компании (списка).
     * @throws \App\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateContactCampaign(string $contactId, string $email, string $campaignId): void
    {
        $this->request(
            "POST /contacts/$contactId",
            'POST',
            ['email' => $email, 'campaign' => ['campaignId' => $campaignId]]
        );
    }
}
