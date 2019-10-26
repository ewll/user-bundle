<?php namespace Ewll\UserBundle\Oauth;

use Ewll\UserBundle\Controller\OauthController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

abstract class AbstractOauth implements OauthInterface
{
    protected $parameters;
    /** @var RouterInterface */
    private $router;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function getRedirectUrl()
    {
        $url = 'https:' . $this->router->generate(
                OauthController::ROUTE_NAME_OAUTH,
                ['name' => $this->getType()],
                UrlGeneratorInterface::NETWORK_PATH
            );

        return $url;
    }
}
