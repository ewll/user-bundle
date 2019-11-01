<?php namespace Ewll\UserBundle\Form\DataTransformer;

use Ewll\UserBundle\Entity\Token;
use Ewll\UserBundle\Token\Exception\TokenNotFoundException;
use Ewll\UserBundle\Token\TokenProvider;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class CodeToTokenTransformer implements DataTransformerInterface
{
    private $tokenProvider;

    public function __construct(TokenProvider $tokenProvider)
    {
        $this->tokenProvider = $tokenProvider;
    }

    public function transform($token)
    {
        /** @var Token|null $user */
        if (null === $token) {
            return null;
        }

        return $this->tokenProvider->compileTokenCode($token);
    }

    public function reverseTransform($code)
    {
        if (null === $code) {
            return null;
        }

        try {
            $token = $this->tokenProvider->getByCode($code);
        } catch (TokenNotFoundException $e) {
            $failure = new TransformationFailedException('Token not found');
            $failure->setInvalidMessage('token.incorrect-or-old');

            throw $failure;
        }

        return $token;
    }
}
