<?php namespace Ewll\UserBundle\Oauth;

use Ewll\UserBundle\Oauth\Exception\EmailNotReceivedException;
use Ewll\UserBundle\Oauth\Exception\WrongCodeException;

interface OauthInterface
{
    public function getType(): string;
    public function getId(): int;
    public function getUrl(): string;
    /**
     * @throws WrongCodeException
     * @throws EmailNotReceivedException
     */
    public function getEmailByCode(string $code): string;
}
