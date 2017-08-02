<?php

error_reporting(E_ALL);
require __DIR__ . "/../vendor/autoload.php";


class StdinFilerServer {
    public function __construct()
    {

    }
}

$injector = new \Auryn\Injector();
$injector
    ->alias(Aerys\HttpDriver::class, Aerys\Http1Driver::class)
    ->alias(Psr\Log\LoggerInterface::class, Aerys\ConsoleLogger::class)
//    ->alias(Psr\Log\LoggerInterface::class, Psr\Log\NullLogger::class)
    ->share($logger = $injector->make(Psr\Log\LoggerInterface::class))
    ->share($vhostContainer = $injector->make(Aerys\VhostContainer::class))
    ->share($server = $injector->make(Aerys\Server::class))
;

$responder = function(Aerys\Request $req, Aerys\Response $resp) use ($logger, $handle) {
    $logger->info(sprintf('%s connected', $req->getConnectionInfo()['server_addr']));

    /** @var \Amp\File\Handle $handle */
    $handle = yield Amp\File\open('/media/gadelat/sdata/Downloads/Arrival.2016 .720p.HDRip.950MB.MkvCage.mkv', 'r');

    while (null !== $chunk = yield $handle->read()) {
        yield $resp->write($chunk);
    }
};

$vhostContainer->use(new Aerys\Vhost('', [["0.0.0.0", 1337]], $responder, []));

Amp\Loop::run(function() use ($server) {
    yield \Amp\call(function() use ($server) {
        yield $server->start();
    });
});