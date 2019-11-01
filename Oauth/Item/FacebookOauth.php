<?php namespace Ewll\UserBundle\Oauth\Item;

use Ewll\UserBundle\Oauth\AbstractOauth;
use Ewll\UserBundle\Oauth\Exception\EmailNotReceivedException;
use Ewll\UserBundle\Oauth\Exception\WrongCodeException;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use RuntimeException;

class FacebookOauth extends AbstractOauth
{
    public function getType(): string
    {
        return 'facebook';
    }

    public function getId(): int
    {
        return 3;
    }

    public function getUrl(): string
    {
        $client = $this->clientFactory();
        $helper = $client->getRedirectLoginHelper();
        $url = $helper->getLoginUrl($this->getRedirectUrl(), ['email']);

        return $url;
    }

    /** @inheritdoc */
    public function getEmailByCode(string $code): string
    {
        $client = $this->clientFactory();
        $helper = $client->getRedirectLoginHelper();
        if (isset($_GET['state'])) {
            $helper->getPersistentDataHandler()->set('state', $_GET['state']);
        }
        try {
            $token = $helper->getAccessToken($this->getRedirectUrl());
        } catch (FacebookResponseException|FacebookSDKException $e) {
            throw new WrongCodeException('', 0, $e);
        }
        if (!isset($token)) {
            throw new WrongCodeException($helper->getError());
        }

        $client->setDefaultAccessToken($token);
        $response = $client->get('/me?fields=email');
        try {
            $userNode = $response->getGraphUser();
        } catch (FacebookSDKException $e) {
            throw new EmailNotReceivedException($e->getMessage(), $e->getCode(), $e);
        }
        $email = $userNode->getField('email');
        if (empty($email)) {
            throw new EmailNotReceivedException();
        }

        return $email;
    }

    private function clientFactory()
    {
        try {
            $client = new Facebook([
                'app_id' => $this->parameters['client_id'],
                'app_secret' => $this->parameters['client_secret'],
                'default_graph_version' => 'v3.2',
            ]);
        } catch (FacebookSDKException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return $client;
    }
}
