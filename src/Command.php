<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Amp\Loop;
use Ostrolucky\Stdinho\Bufferer\PipeBufferer;
use Ostrolucky\Stdinho\Bufferer\ResolvedBufferer;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function Amp\asyncCoroutine;
use function Amp\Socket\listen;

class Command extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var bool
     */
    private $hasStdin = false;
    /**
     * @var string[]
     */
    private $customHttpHeaders = [];

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
            ->addOption(
                'http-headers',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom HTTP headers to append to response (in JSON format). Eg. --http-headers=\'["Content-encoding: gzip"]\'',
                '[]'
            )
            ->addOption(
                'connections-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Determines after how many client connections should program shut down',
                INF
            )
            ->setDescription('Turn any STDIN/STDOUT into HTTP server')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $filePath = $input->getOption('file');
        $json = $input->getOption('http-headers');
        $this->hasStdin = ftell(STDIN) !== false && !stream_isatty(STDIN);
        $this->customHttpHeaders = @json_decode($json, true);

        if (!is_array($this->customHttpHeaders)) {
            throw new InvalidOptionException(
                sprintf('Invalid JSON "%s" has been used in --http-headers option', $json)
            );
        }

        if ($this->hasStdin) {
            return;
        }

        if (!$filePath) {
            throw new InvalidOptionException(
                'Please pipe stdin into '.APP_NAME.', or provide file path via --file option'
            );
        }

        if (!file_exists($filePath)) {
            throw new InvalidOptionException(sprintf('Path "%s" does not exist!', $filePath));
        }
    }

    /**
     * @param ConsoleOutput $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($firstSection = $output->section());

        $bufferer = $this->hasStdin ?
            new PipeBufferer($logger, STDIN, $input->getOption('file'), $output->section()) :
            new ResolvedBufferer($input->getOption('file'))
        ;

        $bufferHandler = asyncCoroutine($bufferer);
        $clientHandler = asyncCoroutine(new Responder($logger, $bufferer, $output, $this->customHttpHeaders));

        Loop::run(function () use ($input, $clientHandler, $logger, $firstSection, $bufferHandler) {
            $bufferHandler();
            $server = listen($input->getArgument('addressPort'));
            $firstSection->writeln(
                "<info>Connection opened at http://{$server->getAddress()}\nPress CTRL+C to exit.</info>\n"
            );
            $connectionsLimit = $input->getOption('connections-limit');
            while ($connectionsLimit-- && ($socket = yield $server->accept())) {
                $clientHandler($socket);
            }
        });

        return 0;
    }
}
