<?php namespace Ewll\UserBundle\Controller;

use Ewll\UserBundle\Twofa\TwofaHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TelegramWebhookController extends AbstractController
{
    const ROUTE_NAME_TELEGRAM_WEBHOOK = 'webhook.telegram.handle';

    private $twofaHandler;

    public function __construct(
        TwofaHandler $twofaHandler
    ) {
        $this->twofaHandler = $twofaHandler;
    }

    public function handle(Request $request) {
        return $this->enrollCodeTelegram($request);
    }

    private function enrollCodeTelegram(Request $request) {
        if ($content = $request->getContent()) {
            $telegramWebhookAsArray = json_decode($content, true);
        }
        $telegramWebhookMessage = $telegramWebhookAsArray['message'];
        $telegramWebhookMessageChat = $telegramWebhookMessage['chat'];
        $telegramUserChatId = $telegramWebhookMessageChat['id'];
        $this->twofaHandler->provideTokenToContact($telegramUserChatId, $request->getClientIp());

        return new JsonResponse([]);
    }
}
