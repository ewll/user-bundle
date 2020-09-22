<?php namespace Ewll\UserBundle\Twofa\Item;

use Ewll\UserBundle\Twofa\Exception\CannotSendMessageException;
use Ewll\UserBundle\Twofa\StoredKeyTwofaInterface;
use Ewll\UserBundle\Twofa\TwofaInterface;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;
use Telegram\Bot\Api;

class TelegramTwofa implements StoredKeyTwofaInterface
{
    const ERROR_DESCRIPTION_CHAT_NOT_FOUND = 'Bad Request: chat not found';
    const TELEGRAM_TWOFA_TYPE = 'telegram';

    private $guzzle;
    private $telegramBot;
    private $proxy;

    public function __construct(string $telegramBotToken, string $proxy = null)
    {
        $this->guzzle = new Guzzle();
        $this->telegramBot = new Api($telegramBotToken);
        $this->proxy = $proxy;
    }

    public function getId(): int
    {
        return 1;
    }

    public function getType(): string
    {
        return 'telegram';
    }

    /** @inheritdoc */
    public function sendMessage(string $contact, string $message): void
    {
        try {
            $params = [
                'chat_id' => $contact,
                'text' => $message,
            ];
            if (!empty($this->proxy)) {
                $proxy = parse_url($this->proxy);
                $options['curl'] = [
                    CURLOPT_PROXY => $proxy['host'],
                    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
                    CURLOPT_PROXYPORT => $proxy['port'],
                    CURLOPT_PROXYUSERPWD => "{$proxy['user']}:{$proxy['pass']}",
                ];
            }
            $this->telegramBot->sendMessage($params);
        } catch (\Exception $e) {
            $response = $e->getResponse();
            $statusCode = null === $response ? null : $response->getStatusCode();
            $code = 0;
            if ($statusCode === 400) {
                $responseData = json_decode($response->getBody(), true);
                if ($responseData['description'] === self::ERROR_DESCRIPTION_CHAT_NOT_FOUND) {
                    $code = CannotSendMessageException::CODE_RECIPIENT_NOT_EXISTS;
                }
            }

            throw new CannotSendMessageException("Request code: $statusCode. Message: {$e->getMessage()}", $code);
        }
    }
}
