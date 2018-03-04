<?php

namespace Ostrolucky\StdinFileServer;

class ConsoleOutput extends \Symfony\Component\Console\Output\ConsoleOutput
{
    private $consoleSectionOutputs = [];

    public function section(): ConsoleSectionOutput
    {
        return new ConsoleSectionOutput($this->getStream(), $this->consoleSectionOutputs, $this->getVerbosity(), $this->isDecorated(), $this->getFormatter());
    }
}