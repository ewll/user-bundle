<?php namespace Ewll\UserBundle\Captcha;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Entity\Token;
use Ewll\UserBundle\Token\Exception\TokenNotFoundException;
use Ewll\UserBundle\Token\Item\CaptchaToken;
use Ewll\UserBundle\Token\TokenProvider;
use Symfony\Component\HttpFoundation\RequestStack;

class CaptchaProvider
{
    const IMG_HEIGHT = 45;

    const SESSION_COOKIE_NAME = 'c';

    private $repositoryProvider;
    private $tokenProvider;
    private $requestStack;
    private $salt;
    private $domain;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        TokenProvider $tokenProvider,
        RequestStack $requestStack,
        string $salt,
        string $domain
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->tokenProvider = $tokenProvider;
        $this->requestStack = $requestStack;
        $this->salt = $salt;
        $this->domain = $domain;
    }

    public function provide(int $size, string $ip, array $color): string
    {
        $value = random_int(20, 100);
        $tokenData = ['ip' => $ip, 'value' => $value];
        $token = $this->tokenProvider->generate(CaptchaToken::class, $tokenData, $ip);
        $tokenCode = $this->tokenProvider->compileTokenCode($token);
        SetCookie(self::SESSION_COOKIE_NAME, $tokenCode, time() + 300, '/', $this->domain, true, true);

        $image = $this->generateImage($size, $value, $color);

        return $image;
    }

    public function isValid(int $value): bool
    {
        $token = $this->getToken();
        if (null !== $token) {
            $realValue = $token->data['value'];
            if ($value === $realValue) {
                return true;
            }
        }

        return false;
    }

    public function deactivate()
    {
        $token = $this->getToken();
        if (null !== $token) {
            $this->tokenProvider->toUse($token);
        }
    }

    private function getToken(): ?Token
    {
        $tokenCode = $this->requestStack->getCurrentRequest()->cookies->get(self::SESSION_COOKIE_NAME);
        if (null === $tokenCode) {
            return null;
        }
        try {
            $token = $this->tokenProvider->getByCode($tokenCode, CaptchaToken::TYPE_ID);

            return $token;
        } catch (TokenNotFoundException $e) {
            return null;
        }
    }

    private function generateImage(int $size, int $value, array $color): string
    {
        $image = imagecreatetruecolor($size, self::IMG_HEIGHT);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $backgroundColor = call_user_func_array('imagecolorallocate', array_merge([$image], $color));
        imagefilledrectangle($image, 0, 0, $size, self::IMG_HEIGHT, $backgroundColor);

        $elementsColor = imagecolorallocate($image, 255, 255, 255);;

        $indent = 20;
        $rate = ($size - $indent * 2) / 99.756;
        $position = round($value * $rate + $indent);
        $puzzleMap = [
            5,
            13,
            15,
            19,
            21,
            23,
            25,
            27,
            29,
            31,
            31,
            33,
            33,
            35,
            35,
            35,
            35,
            35,
            35,
            35,
            33,
            33,
            33,
            31,
            31,
            29,
            27,
            25,
            23,
            21,
            19,
            17,
            15,
            13,
            11,
            9,
            7,
            5,
            3,
            1
        ];
        foreach ($puzzleMap as $row => $puzzleRowLength) {
            $rowPx = $row + 2;
            $xStart = $position - $puzzleRowLength / 2;
            for ($i = 0; $i < $puzzleRowLength; $i++) {
                if ($i > 7 && $i < ($puzzleRowLength - 8) && $row > 5) {
                    continue;
                }
                $pointPx = $xStart + $i;
                imagesetpixel($image, $pointPx, $rowPx, $elementsColor);
            }
        }

        $numberOfLines = round($size / 18);

        $offset = rand(20, 50);
        for ($i = 0; $i < $numberOfLines; $i++) {
            $x1 = $size / $numberOfLines * $i + rand(-10, 10);
            $x2 = $x1 + $offset;
            $this->writeLine($image, $x1, $x2, $elementsColor);
        }

        $offset = rand(-50, -20);
        for ($i = $numberOfLines; $i > 0; $i--) {
            $x1 = $size / $numberOfLines * $i + rand(-10, 10);
            $x2 = $x1 + $offset;
            $this->writeLine($image, $x1, $x2, $elementsColor);
        }

        ob_start();
        imagepng($image);
        imagedestroy($image);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    private function writeLine($image, $x1, $x2, $elementsColor)
    {
        for ($i = 0; $i < 6; $i++) {
            $rx1 = $x1 + $i;
            $rx2 = $x2 + $i;
            imageline($image, $rx1, 0, $rx2, self::IMG_HEIGHT, $elementsColor);
        }
    }
}
