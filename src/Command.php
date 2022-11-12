<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\Server;
use Ostrolucky\Stdinho\Bufferer\AbstractBufferer;
use Ostrolucky\Stdinho\Bufferer\PipeBufferer;
use Ostrolucky\Stdinho\Bufferer\ResolvedBufferer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function Amp\asyncCoroutine;

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
            ->addOption(
                'buffer-size',
                'b',
                InputOption::VALUE_REQUIRED,
                'Buffer size in bytes. By default, it is 90% of available disk space'
            )
            ->setDescription('Turn any STDIN/STDOUT into HTTP server')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $filePath = $input->getOption('file');
        $json = $input->getOption('http-headers');
        $fileExists = $filePath && file_exists($filePath);
        $this->hasStdin = !stream_isatty(STDIN);
        $this->customHttpHeaders = @json_decode($json, true);

        if (!is_array($this->customHttpHeaders)) {
            throw new InvalidOptionException(
                sprintf('Invalid JSON "%s" has been used in --http-headers option', $json)
            );
        }

        if ($this->hasStdin) {
            if ($fileExists) {
                // both stdin and existing file path provided, so we can't deterministically decide user wants to overwrite file
                // they might want to overwrite file, or they might want to read from it - no way to know
                throw new LogicException(
                    sprintf('File "%s" exists! If you want to overwrite it, please remove it beforehand.', $filePath)
                );
            }

            return;
        }

        if (!$filePath) {
            throw new InvalidOptionException(
                'Please pipe stdin into '.APP_NAME.', or provide file path via --file option'
            );
        }

        if (!$fileExists) {
            throw new InvalidOptionException(sprintf('Path "%s" does not exist!', $filePath));
        }
    }

    /**
     * @param ConsoleOutput $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connectionsLimit = (float)$input->getOption('connections-limit');
        $newConnDefer = new Deferred();
        $bufferer = $this->createBufferer(
            $output,
            $logger = new ConsoleLogger($firstSection = $output->section()),
            $server = Server::listen($input->getArgument('addressPort')),
            $newConnDefer->promise(),
            $input->getOption('file'),
            $input->getOption('buffer-size'),
        );

        $firstSection->writeln(
            "<info>Connection opened at http://{$server->getAddress()}\nPress CTRL+C to exit.</info>\n"
        );

        Loop::run(function() use ($newConnDefer, &$connectionsLimit, $server, $logger, $output, $bufferer) {
            asyncCoroutine($bufferer)();

            while ($connectionsLimit-- && ($socket = yield $server->accept())) {
                $responder = new Responder(
                    $logger,
                    $bufferer,
                    $output,
                    $this->customHttpHeaders,
                    new ResourceInputStream(fopen($bufferer->filePath, 'rb')),
                    $newConnDefer
                );
                asyncCoroutine($responder)($socket);
            }
        });

        return 0;
    }

    private function createBufferer(
        ConsoleOutput $output,
        LoggerInterface $logger,
        Server $server,
        Promise $promiseThatIsResolvedWhenSomebodyConnects,
        ?string $filePath,
        ?string $bufferSize
    ): AbstractBufferer {
        if (!$this->hasStdin) {
            return new ResolvedBufferer($filePath);
        }

        return new PipeBufferer(
            new ResourceInputStream(STDIN),
            $output->section(),
            $logger,
            new ResourceOutputStream($filePath ? fopen($filePath, 'wb') : tmpfile()),
            $server,
            $promiseThatIsResolvedWhenSomebodyConnects,
            (int)($bufferSize ?? disk_free_space($filePath ?: sys_get_temp_dir()) * .9)
        );
    }
}
