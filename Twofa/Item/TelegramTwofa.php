<?php namespace Ewll\UserBundle\Twofa\Item;

use Ewll\UserBundle\Twofa\Exception\CannotSendMessageException;
use Ewll\UserBundle\Twofa\StoredKeyTwofaInterface;
use Ewll\UserBundle\Twofa\TwofaInterface;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;

class TelegramTwofa implements StoredKeyTwofaInterface
{
    const ERROR_DESCRIPTION_CHAT_NOT_FOUND = 'Bad Request: chat not found';

    private $guzzle;
    private $telegramBotToken;
    private $proxy;

    public function __construct(string $telegramBotToken, string $proxy = null)
    {
        $this->guzzle = new Guzzle();
        $this->telegramBotToken = $telegramBotToken;
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
        $url = "https://api.telegram.org/bot$this->telegramBotToken/sendMessage";
        try {
            $params = [
                'chat_id' => $contact,
                'text' => $message,
            ];
            $options = [
                'timeout' => 6,
                'connect_timeout' => 6,
                'query' => $params,
            ];
            if (null !== $this->proxy) {
                $proxy = parse_url($this->proxy);
                $options['curl'] = [
                    CURLOPT_PROXY => $proxy['host'],
                    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
                    CURLOPT_PROXYPORT => $proxy['port'],
                    CURLOPT_PROXYUSERPWD => "{$proxy['user']}:{$proxy['pass']}",
                ];
            }
            $request = $this->guzzle->get($url, $options);
            $content = $request->getBody()->getContents();
            $contentData = json_decode($content, true);
            if (true !== $contentData['ok']) {
                throw new CannotSendMessageException($content);
            }
        } catch (RequestException $e) {
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
