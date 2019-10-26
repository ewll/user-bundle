<?php namespace Ewll\UserBundle\Twofa\Item;

use Ewll\UserBundle\Twofa\CheckKeyOnTheFlyTwofaInterface;
use Ewll\UserBundle\Twofa\Exception\IncorrectTwofaCodeException;
use Ewll\UserBundle\Twofa\Item\Google\FixedBitNotation;

class GoogleTwofa implements CheckKeyOnTheFlyTwofaInterface
{
    public function __construct()
    {
    }

    public function getId(): int
    {
        return 2;
    }

    public function getType(): string
    {
        return 'google';
    }

    public function compileDataFromContext(string $context): array
    {
        $data = ['secret' => $context];

        return $data;
    }

    public function getSecretUrl($user, $hostname, $secret): string
    {
        $data = http_build_query([
            'chs' => '200x200',
            'chld' => 'M|0',
            'cht' => 'qr',
            'chl' => "otpauth://totp/$user@$hostname?secret=$secret",
        ]);
        $url = "https://chart.googleapis.com/chart?$data";

        return $url;
    }

    public function generateSecret()
    {
        $secret = '';
        for ($i = 1; $i <= 10; $i++) {
            $c = rand(0, 255);
            $secret .= pack("c", $c);
        }
        $base32 = new FixedBitNotation(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', true, true);

        return $base32->encode($secret);
    }


    public function isCodeCorrect(array $data, string $code): bool
    {
        $time = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {

            if ($this->getCode($data['secret'], $time + $i) == $code) {
                return true;
            }
        }

        return false;
    }

    private  function hashToInt($bytes, $start) {
        $input = substr($bytes, $start, strlen($bytes) - $start);
        $val2 = unpack("N",substr($input,0,4));
        return $val2[1];
    }

    private function getCode(string $secret, $time = null): string
    {
        if (!$time) {
            $time = floor(time() / 30);
        }
        $base32 = new FixedBitNotation(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', true, true);
        $secret = $base32->decode($secret);

        $time = pack("N", $time);
        $time = str_pad($time, 8, chr(0), STR_PAD_LEFT);

        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord(substr($hash, -1));
        $offset = $offset & 0xF;

        $truncatedHash = self::hashToInt($hash, $offset) & 0x7FFFFFFF;
        $pinModulo = pow(10, 6);
        $pinValue = str_pad($truncatedHash % $pinModulo, 6, '0', STR_PAD_LEFT);;

        return $pinValue;
    }
}
