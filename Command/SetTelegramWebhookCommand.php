<?php namespace Ewll\UserBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Ewll\UserBundle\Controller\TelegramWebhookController;
use Telegram\Bot\Api;

class SetTelegramWebhookCommand extends Command
{
    private $router;
    private $telegramBot;

    public function __construct(
        string $telegramBotToken,
        Router $router
    ) {
        parent::__construct();
        $this->telegramBot = new Api($telegramBotToken);
        $this->router = $router;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $this->router->generate(
            TelegramWebhookController::ROUTE_NAME_TELEGRAM_WEBHOOK,
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $this->telegramBot->setWebhook([
            'url' => $url
        ]);
        $output->writeln('Telegram bot webhook url added successfully');

        return 0;
    }
}
