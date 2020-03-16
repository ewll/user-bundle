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
        $size = $size < 200 || $size > 800 ? 800 : $size;
        $color = $request->query->get('color', '');
        if (1 === preg_match('/^([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $color, $colorMatches)) {
            $color = [hexdec($colorMatches[1]), hexdec($colorMatches[2]), hexdec($colorMatches[3])];
        } else {
            $color = [178, 223, 219];
        }

        $headers = ['Content-type' => 'image/png'];
        $image = $this->captchaProvider->provide($size, $request->getClientIp(), $color);

        return new Response($image, Response::HTTP_OK, $headers);
    }
}
