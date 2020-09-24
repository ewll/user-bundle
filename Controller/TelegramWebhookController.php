<?php namespace Ewll\UserBundle\Controller;

use Ewll\UserBundle\Twofa\Exception\CannotProvideCodeException;
use Ewll\UserBundle\Twofa\TwofaHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([]);
    }
}
