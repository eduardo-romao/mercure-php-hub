<?php

namespace BenTools\MercurePHP\Transport\PHP;

use BenTools\MercurePHP\Transport\PHP\PHPTransport;
use BenTools\MercurePHP\Transport\TransportFactoryInterface;
use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class PHPTransportFactory implements TransportFactoryInterface
{
    /**
     * @var EventEmitterInterface
     */
    private EventEmitterInterface $emitter;

    public function __construct(?EventEmitterInterface $emitter = null)
    {
        $this->emitter = $emitter ?? new EventEmitter();
    }

    public function supports(string $dsn): bool
    {
        return 0 === \strpos($dsn, 'php://');
    }

    public function create(string $dsn, LoopInterface $loop): PromiseInterface
    {
        return resolve(new PHPTransport($this->emitter));
    }
}