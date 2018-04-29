<?php

namespace Ostrolucky\Stdinho;

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Ostrolucky\Stdinho\Bufferer\PipeBufferer;
use Ostrolucky\Stdinho\Bufferer\ResolvedBufferer;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Command extends \Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this
            ->addArgument('addressPort', InputArgument::OPTIONAL, 'The address:port to listen to', '0.0.0.0:*')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'File path. If no stdin is provided, this is used as source. Otherwise, it is target where stdin is copied to')
            ->setDescription('Open HTTP portal to your standard input stream')
        ;
    }

    /**
     * @param ConsoleOutput $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = $input->getOption('file');
        $hasStdin = ftell($stdin = STDIN) !== false;

        $errorHandler = function($message) use ($input, $output) {
            (new DescriptorHelper())->describe($output, $this);
            (new SymfonyStyle($input, $output))->error($message);
        };

        if (!$hasStdin && !$filePath) {
            $errorHandler('Please pipe stdin into '.APP_NAME.', or provide file path via --file option');

            return 1;
        } elseif (!$hasStdin && $filePath && !file_exists($filePath)) {
            $errorHandler("Path $filePath does not exist!");

            return 1;
        }

        $logger = new ConsoleLogger($firstSection = $output->section());

        if ($hasStdin) {
            $bufferer = new PipeBufferer(
                $logger,
                new ResourceInputStream(STDIN),
                new ResourceOutputStream($filePath ? fopen($filePath, 'w') : tmpfile()),
                $output->section()
            );
        } else {
            $bufferer = new ResolvedBufferer($filePath);
        }


        $bufferHandler = \Amp\asyncCoroutine($bufferer);
        $clientHandler = \Amp\asyncCoroutine(new Responder($logger, $bufferer, $output));
        $addressPort = $input->getArgument('addressPort');

        \Amp\Loop::run(function() use ($addressPort, $clientHandler, $logger, $firstSection, $bufferHandler) {
            $bufferHandler();
            $server = \Amp\socket\listen($addressPort);
            $firstSection->writeln('<info>Connection opened at http://' . $server->getAddress() . '</info>');
            while ($socket = yield $server->accept()) {
                $clientHandler($socket);
            }
        });

        return 0;
    }
}