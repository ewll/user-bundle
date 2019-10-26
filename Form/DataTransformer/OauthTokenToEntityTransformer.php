<?php namespace Ewll\UserBundle\Form\DataTransformer;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Entity\OauthToken;
use Ewll\UserBundle\Repository\OauthTokenRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class OauthTokenToEntityTransformer implements DataTransformerInterface
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function transform($entity)
    {
        /** @var OauthToken|null $entity */
        if (null === $entity) {
            return null;
        }

        return $entity->token;
    }

    public function reverseTransform($token)
    {
        if (null === $token) {
            return null;
        }

        /** @var OauthTokenRepository $oauthTokenRepository */
        $oauthTokenRepository = $this->repositoryProvider->get(OauthToken::class);
        /** @var OauthToken $entity */
        $entity = $oauthTokenRepository->findActiveByToken($token);

        if (null === $entity) {
            $failure = new TransformationFailedException('User not found');
            $failure->setInvalidMessage('oauth-token.incorrect-or-old');

            throw $failure;
        }

        return $entity;
    }
}
