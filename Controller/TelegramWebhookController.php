<?php namespace Ewll\UserBundle\Controller;

use Ewll\UserBundle\Twofa\Exception\CannotProvideCodeException;
use Ewll\UserBundle\Twofa\TwofaHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TelegramWebhookController extends AbstractController
{
    const ROUTE_NAME_TELEGRAM_WEBHOOK = 'webhook.telegram.handle';
    const TELEGRAM_WEBHOOK_NOT_HANDLED_STATUS_CODE = 400;
    const TELEGRAM_WEBHOOK_HANDLED_SUCCESSFULLY_STATUS_CODE = 200;

    private $twofaHandler;

    public function __construct(
        TwofaHandler $twofaHandler
    ) {
        $this->twofaHandler = $twofaHandler;
    }

    public function handle(Request $request) {
        return $this->sendRegistrationTokenToUserByTelegram($request);
    }

    private function sendRegistrationTokenToUserByTelegram(Request $request) {
        if ($content = $request->getContent()) {
            $telegramWebhookAsArray = json_decode($content, true);
        }
        $telegramWebhookMessage = $telegramWebhookAsArray['message'];
        $telegramWebhookMessageChat = $telegramWebhookMessage['chat'];
        $telegramUserChatId = $telegramWebhookMessageChat['id'];
        try {
            $this->twofaHandler->provideTokenToContact($telegramUserChatId, $request->getClientIp());
        } catch (CannotProvideCodeException $exception) {
            return new JsonResponse([], self::TELEGRAM_WEBHOOK_NOT_HANDLED_STATUS_CODE);
        }

        return new JsonResponse([], self::TELEGRAM_WEBHOOK_HANDLED_SUCCESSFULLY_STATUS_CODE);
    }
}
