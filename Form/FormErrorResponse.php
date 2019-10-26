<?php namespace Ewll\UserBundle\Form;

use Ewll\UserBundle\Form\Constraints\CsrfToken;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use LogicException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FormErrorResponse extends JsonResponse
{
    public function __construct(FormInterface $form)
    {
        if ($form->isValid()) {
            throw new LogicException('The form must not be valid here!');
        }

        $code = Response::HTTP_BAD_REQUEST;
        $data = [];
        $errors = $form->getErrors(true);
        $view = [];
        foreach ($errors as $error) {
            $cause = $error->getCause();
            if (null !== $cause) {
                if ($cause instanceof IncorrectTwofaCodeException) {
                    $code = Response::HTTP_PRECONDITION_FAILED;
                    $data['twofa'] = ['isStoredCode' => $cause->isStoredKey(), 'actionId' => $cause->getActionId()];
                } else {
                    $constraint = $error->getCause()->getConstraint();
                    if ($constraint instanceof CsrfToken) {
                        $code = $error->getMessageParameters()[CsrfToken::MESSAGE_PARAMETER_KEY_CODE];
                        switch ($code) {
                            case CsrfToken::CODE_NOT_AUTHORIZED:
                                $code = Response::HTTP_UNAUTHORIZED;
                                break;
                            case CsrfToken::CODE_CSRF_NOT_VALID:
                                $code = Response::HTTP_FORBIDDEN;
                                break;
                        }
                    }
                }
            }
            $view[$error->getOrigin()->getName()] = $error->getMessage();
        }
        $data = ['errors' => $view, 'data' => $data];

        parent::__construct($data, $code);
    }
}
