<?php

error_reporting(E_ALL);
require __DIR__ . "/../vendor/autoload.php";


class StdinFilerServer {
    public function __construct()
    {

    }
}

$container = \DI\ContainerBuilder::buildDevContainer();
$logger = $container->get(\Aerys\ConsoleLogger::class);
stream_set_blocking(STDIN, false);

$handle = tmpfile();
$tmpFilePath = $tmpFilePath = stream_get_meta_data($handle)['uri'];

Amp\Loop::onReadable(STDIN, function($watcherId, $stream) use ($handle, $logger) {
    if (!$data = fread($stream, 4086)) {
        Amp\Loop::cancel($watcherId);
        $logger->info('Stdin transfer done');
    }

    fputs($handle, $data);
});

$responder = function(Aerys\Request $req, Aerys\Response $resp) use ($logger, $tmpFilePath) {
    $ip = $req->getConnectionInfo()['server_addr'];
    $logger->info(sprintf('%s connected', $ip));

    $resp->setHeader('content-type', 'application/octet-stream');

    /** @var \Amp\File\Handle $handle */
    $handle = yield Amp\File\open($tmpFilePath, 'r');

    while (null !== $chunk = yield $handle->read()) {
        yield $resp->write($chunk);
    }

    $logger->info(sprintf('%s finished downloading', $ip));
};

$server = Aerys\initServer($logger, [(new \Aerys\Host)->expose('*', 1337)->use($responder)], ['debug' => true]);

Amp\Loop::run(function() use ($server) {
    yield \Amp\call(function() use ($server) {
        yield $server->start();
    });
});