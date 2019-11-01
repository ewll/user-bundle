<?php namespace Ewll\UserBundle\Oauth\Item;

use Ewll\UserBundle\Oauth\AbstractOauth;
use Ewll\UserBundle\Oauth\Exception\EmailNotReceivedException;
use Ewll\UserBundle\Oauth\Exception\WrongCodeException;
use Google_Client;
use Google_Service_Oauth2;

class GoogleOauth extends AbstractOauth
{
    public function getType(): string
    {
        return 'google';
    }

    public function getId(): int
    {
        return 1;
    }

    public function getUrl(): string
    {
        $client = $this->clientFactory();
        $url = $client->createAuthUrl();

        return $url;
    }

    /** @inheritdoc */
    public function getEmailByCode(string $code): string
    {
        $client = $this->clientFactory();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (!isset($token['access_token'])) {
            throw new WrongCodeException();
        }
        $client->setAccessToken($token['access_token']);
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        if (empty($google_account_info->email)) {
            throw new EmailNotReceivedException();
        }
        $email = $google_account_info->email;

        return $email;
    }

    private function clientFactory()
    {
        $client = new Google_Client();
        $client->setClientId($this->parameters['client_id']);
        $client->setClientSecret($this->parameters['client_secret']);
        $client->setRedirectUri($this->getRedirectUrl());
        $client->addScope('email');
        $client->addScope('profile');

        return $client;
    }
}
