<?php

namespace App\Services;

use App\Exceptions\ApiException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Сервис взаимодействия с API Getresponse.
 *
 * Используется для работы в сервисе рассылок getresponse.com, например, для работы с контактами.
 */
class GetresponseService extends BaseApiService
{
    const CASE_1_LEAD_CAMPAIGN_ID = 'oPIZt';
    const CASE_1_SOLD_CAMPAIGN_ID = 'oPIWi';
    const LOGIN_URL_FIELD_ID = 'Vtw43s';

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
     * @param string $loginUrl Ссылка для авторизации.
     * @throws \App\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createContact(string $email, string $campaignId, string $loginUrl)
    {
        $this->request(
            '/contacts',
            'POST',
            [
                'email' => $email,
                'campaign' => ['campaignId' => $campaignId],
                'customFieldValues' => [
                    ['customFieldId' => self::LOGIN_URL_FIELD_ID, 'value' => [$loginUrl], 'values' => [$loginUrl]]
                ]
            ]
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
    public function updateContactCampaignByContactId(string $contactId, string $email, string $campaignId): void
    {
        $this->request(
            "POST /contacts/$contactId",
            'POST',
            ['email' => $email, 'campaign' => ['campaignId' => $campaignId]]
        );
    }

    /**
     * Поиск и изменение компании по адресу электронной почты.
     *
     * @param string $email Адрес электронной почты.
     * @param string $campaignId Id компании (списка).
     * @throws ApiException
     * @throws GuzzleException
     */
    public function updateContactCampaignByEmail(string $email, string $campaignId): void
    {
        $contacts = $this->getContactByEmailFromCampaign($email, $campaignId);

        if ($contacts === []) {
            throw new ApiException("Contacts by $email not found!");
        }

        $this->updateContactCampaignByContactId($contacts[0]["contactId"], $email, $campaignId);
    }
}
