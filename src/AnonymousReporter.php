<?php
namespace Peridot\Reporter;

use Peridot\EventEmitterInterface;
use Peridot\Configuration;
use Peridot\Core\Context;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The AnonymousReporter creates a reporter from a PHP callable.
 *
 * @package Peridot\Reporter
 */
class AnonymousReporter extends AbstractBaseReporter
{
    /**
     * @var callable
     */
    protected $initFn;

    /**
     * Creates a reporter from a callable
     *
     * @param callable $init
     * @param OutputInterface $output
     * @param EventEmitterInterface $eventEmitter
     */
    public function __construct(
        callable $init,
        OutputInterface $output,
        EventEmitterInterface $eventEmitter,
        Context $context
    ) {
        $this->initFn = $init;
        parent::__construct($output, $eventEmitter, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function init()
    {
        call_user_func($this->initFn, $this);
    }
}
