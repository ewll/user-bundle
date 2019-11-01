<?php namespace Ewll\UserBundle\Oauth\Item;

use Ewll\UserBundle\Oauth\AbstractOauth;
use Ewll\UserBundle\Oauth\Exception\EmailNotReceivedException;
use Ewll\UserBundle\Oauth\Exception\WrongCodeException;
use VK\Exceptions\VKClientException;
use VK\Exceptions\VKOAuthException;
use VK\OAuth\Scopes\VKOAuthUserScope;
use VK\OAuth\VKOAuth as VKOAuthSdk;
use VK\OAuth\VKOAuthDisplay;
use VK\OAuth\VKOAuthResponseType;

class VkOauth extends AbstractOauth
{
    public function getType(): string
    {
        return 'vk';
    }

    public function getId(): int
    {
        return 2;
    }

    public function getUrl(): string
    {
        $client = $this->clientFactory();
        $url = $client->getAuthorizeUrl(
            VKOAuthResponseType::CODE,
            $this->parameters['client_id'],
            $this->getRedirectUrl(),
            VKOAuthDisplay::PAGE,
            [VKOAuthUserScope::EMAIL],
            null,
            null,
            true
        );

        return $url;
    }

    /** @inheritdoc */
    public function getEmailByCode(string $code): string
    {
        $client = $this->clientFactory();
        try {
            $token = $client->getAccessToken(
                $this->parameters['client_id'],
                $this->parameters['client_secret'],
                $this->getRedirectUrl(),
                $code
            );
        } catch (VKClientException|VKOAuthException $e) {
            throw new WrongCodeException('', 0, $e);
        }

        if (empty($token['email'])) {
            throw new EmailNotReceivedException();
        }
        $email = $token['email'];

        return $email;
    }

    private function clientFactory()
    {
        $client = new VKOAuthSdk();

        return $client;
    }
}
