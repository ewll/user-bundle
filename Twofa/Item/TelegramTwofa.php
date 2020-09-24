<?php namespace Ewll\UserBundle\Twofa\Item;

use Ewll\UserBundle\Twofa\Exception\CannotSendMessageException;
use Ewll\UserBundle\Twofa\StoredKeyTwofaInterface;
use GuzzleHttp\Exception\RequestException;
use Telegram\Bot\Api;

class TelegramTwofa implements StoredKeyTwofaInterface
{
    const ERROR_DESCRIPTION_CHAT_NOT_FOUND = 'Bad Request: chat not found';

    private $telegramBot;

    public function __construct(string $telegramBotToken)
    {
        $this->telegramBot = new Api($telegramBotToken);
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
            $this->telegramBot->sendMessage($params);
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
