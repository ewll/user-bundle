<?php namespace Ewll\UserBundle\Controller;

use Ewll\UserBundle\Captcha\CaptchaProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptchaController extends AbstractController
{
    private $captchaProvider;

    public function __construct(
        CaptchaProvider $captchaProvider
    ) {
        $this->captchaProvider = $captchaProvider;
    }

    public function captcha(Request $request)
    {
        $size = $request->query->getInt('size', 0);
        $size = $size < 200 || $size > 800 ? 500 : $size;

        $headers = ['Content-type' => 'image/png'];
        $image = $this->captchaProvider->provide($size, $request->getClientIp());

        return new Response($image, Response::HTTP_OK, $headers);
    }
}
