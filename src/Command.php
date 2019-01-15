<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Amp\Loop;
use Ostrolucky\Stdinho\Bufferer\PipeBufferer;
use Ostrolucky\Stdinho\Bufferer\ResolvedBufferer;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Amp\asyncCoroutine;
use function Amp\Socket\listen;

class Command extends \Symfony\Component\Console\Command\Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('addressPort', InputArgument::OPTIONAL, 'The address:port to listen to', '0.0.0.0:*')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'File path. If no stdin is provided, this is used as source. Otherwise, it is target where stdin is copied to'
            )
            ->setDescription('Open HTTP portal to your standard input stream')
        ;
    }

    /**
     * @param ConsoleOutput $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $addressPort = $input->getArgument('addressPort');
        $filePath = $input->getOption('file');
        $hasStdin = ftell($stdin = STDIN) !== false && !stream_isatty($stdin);

        $errorHandler = function ($message) use ($input, $output): int {
            (new DescriptorHelper())->describe($output, $this);
            (new SymfonyStyle($input, $output))->error($message);

            return 1;
        };

        if (!$hasStdin) {
            if (!$filePath) {
                return $errorHandler('Please pipe stdin into '.APP_NAME.', or provide file path via --file option');
            }

            if (!file_exists($filePath)) {
                return $errorHandler("Path $filePath does not exist!");
            }
        }

        $logger = new ConsoleLogger($firstSection = $output->section());

        $bufferer = $hasStdin ?
            new PipeBufferer($logger, $stdin, $filePath, $output->section()) :
            new ResolvedBufferer($filePath)
        ;

        $bufferHandler = asyncCoroutine($bufferer);
        $clientHandler = asyncCoroutine(new Responder($logger, $bufferer, $output));

        Loop::run(function () use ($addressPort, $clientHandler, $logger, $firstSection, $bufferHandler) {
            $bufferHandler();
            $server = listen($addressPort);
            $firstSection->writeln(
                "<info>Connection opened at http://{$server->getAddress()}\nPress CTRL+C to exit.</info>\n"
            );
            while ($socket = yield $server->accept()) {
                $clientHandler($socket);
            }
        });

        return 0;
    }
}
