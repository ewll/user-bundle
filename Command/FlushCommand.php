<?php namespace Ewll\UserBundle\Command;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Entity\Token;
use Ewll\UserBundle\Entity\TwofaCode;
use Ewll\UserBundle\Repository\TokenRepository;
use Ewll\UserBundle\Repository\TwofaCodeRepository;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FlushCommand extends Command
{
    private $repositoryProvider;
    private $logger;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Logger $logger
    ) {
        $this->repositoryProvider = $repositoryProvider;
        parent::__construct();
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var TokenRepository $tokenRepository */
        $tokenRepository = $this->repositoryProvider->get(Token::class);
        $tokensAffectedNum = $tokenRepository->flush();

        /** @var TwofaCodeRepository $twofaCodeRepository */
        $twofaCodeRepository = $this->repositoryProvider->get(TwofaCode::class);
        $twofaCodesAffectedNum = $twofaCodeRepository->flush();

        $this->logger->info(sprintf('Flushed. Tokens: %d, TwofaCodes: %d', $tokensAffectedNum, $twofaCodesAffectedNum));

        return 0;
    }
}
