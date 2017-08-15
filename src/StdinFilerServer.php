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

Amp\Loop::onReadable(STDIN, $stdinPersister = new \Ostrolucky\StdinFileServer\StdinPersister($logger));

$responder = new \Ostrolucky\StdinFileServer\Responder($logger, $stdinPersister->getHandleFilePath());
$server = Aerys\initServer($logger, [(new \Aerys\Host)->expose('*', 1337)->use($responder)]);

Amp\Loop::run(function() use ($server) {
    yield \Amp\call(function() use ($server) {
        yield $server->start();
    });
});