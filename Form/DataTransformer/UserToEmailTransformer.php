<?php namespace Ewll\UserBundle\Form\DataTransformer;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Entity\User;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class UserToEmailTransformer implements DataTransformerInterface
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function transform($user)
    {
        /** @var User|null $user */
        if (null === $user) {
            return null;
        }

        return $user->email;
    }

    public function reverseTransform($email)
    {
        if (null === $email) {
            return null;
        }

        $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $email]);

        if (null === $user) {
            $failure = new TransformationFailedException('User not found');
            $failure->setInvalidMessage('user-not-found-by-email');

            throw $failure;
        }

        return $user;
    }
}
