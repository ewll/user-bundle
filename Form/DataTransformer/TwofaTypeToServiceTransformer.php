<?php namespace Ewll\UserBundle\Form\DataTransformer;

use Ewll\UserBundle\Twofa\TwofaInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class TwofaTypeToServiceTransformer implements DataTransformerInterface
{
    /** @var TwofaInterface[] */
    private $twofas;

    public function __construct(iterable $twofas)
    {
        $this->twofas = $twofas;
    }

    public function transform($service)
    {
        /** @var TwofaInterface|null $service */
        if (null === $service) {
            return null;
        }

        return $service->getType();
    }

    public function reverseTransform($type)
    {
        if (null === $type) {
            return null;
        }

        foreach ($this->twofas as $twofa) {
            if ($twofa->getType() === $type) {
                return $twofa;
            }
        }

        $failure = new TransformationFailedException('Twofa not found');
        $failure->setInvalidMessage('twofa.provider-not-found');

        throw $failure;
    }
}
