<?php

namespace App\Services;

use App\Exceptions\ApiException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Сервис взаимодействия с API Getresponse.
 *
 * Используется для работы в сервисе рассылок getresponse.com, например, для работы с контактами.
 */
class GetresponseService extends BaseApiService
{
    const CASE_1_LEAD_CAMPAIGN_ID = 'oPIZt';
    const CASE_1_SOLD_CAMPAIGN_ID = 'oPIWi';
    const PERSONAL_ACCOUNT_CAMPAIGN_ID = 'ounRj';
    const LOGIN_URL_FIELD_ID = 'Vtw43s';
    const PAYED_CASE_FIELD_ID = 'Vtw4mg';

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
            "/contacts/$contactId",
            'POST',
            [
                'email' => $email,
                'campaign' => ['campaignId' => $campaignId],
            ]
        );
    }

    /**
     * Изменение компании у контакта.
     *
     * @param string $customFieldId Id настраиваемого поля.
     * @param string $contactId Id контакта в getresponse.
     * @param string $payedCase Оплаченный кейс.
     * @throws \App\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateContactCustomField(string $customFieldId, string $contactId, string $payedCase): void
    {
        $this->request(
            "/contacts/$contactId/custom-fields",
            'POST',
            [
                'customFieldValues' => [
                    ['customFieldId' => $customFieldId, 'value' => [$payedCase]]
                ]
            ]
        );
    }

    /**
     * Вызов события отправки сообщения со ссылкой для входа (send_message_login).
     *
     * @param string $contactId Id контакта в getresponse.
     * @throws \App\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function triggerLoginEvent(string $contactId): void
    {
        $this->request(
            "/custom-events/trigger",
            'POST',
            [
                'name' => 'send_message_login',
                'contactId' => $contactId
            ]
        );
    }

    /**
     * Поиск контакта по адресу электронной почты в "Personal_account" компании.
     *
     * @param string $email
     * @return array
     * @throws ApiException
     * @throws GuzzleException
     */
    public function findInPersonalAccountCampaign(string $email): array
    {
        $contacts = $this->getContactByEmailFromCampaign($email, $this::PERSONAL_ACCOUNT_CAMPAIGN_ID);

        if ($contacts['data'] === []) {
            throw new ApiException("Contacts by $email not found!");
        }

        return $contacts['data'][0];
    }

    /**
     * Поиск контакта по адресу электронной почты в "CASE_1" компаниях.
     *
     * @param string $email
     * @return array
     * @throws ApiException
     * @throws GuzzleException
     */
    public function findInCaseCampaigns(string $email): array
    {
        $contacts = $this->getContactByEmailFromCampaign($email, $this::CASE_1_LEAD_CAMPAIGN_ID);

        if ($contacts['data'] === []) {
            $contacts = $this->getContactByEmailFromCampaign($email, $this::CASE_1_SOLD_CAMPAIGN_ID);
        }

        if ($contacts['data'] === []) {
            throw new ApiException("Contacts by $email not found!");
        }

        return $contacts['data'][0];
    }

    /**
     * Изменение компании по адресу электронной почты, а также обновление кейсов.
     *
     * @param string $email Адрес электронной почты.
     * @param string $campaignId Id компании (списка).
     * @param string $payedCase Оплаченный кейс.
     * @throws ApiException
     * @throws GuzzleException
     */
    public function updateContactCampaignByEmail(string $email, string $campaignId, string $payedCase): void
    {
        $contact = $this->findInCaseCampaigns($email);
        $contactId = $contact["contactId"];
        $this->updateContactCampaignByContactId($contactId, $email, $campaignId);

        $this->updateContactCustomField($this::PAYED_CASE_FIELD_ID, $contactId, $payedCase);
    }
}
