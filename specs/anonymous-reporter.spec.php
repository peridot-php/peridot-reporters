<?php
use Evenement\EventEmitter;
use Peridot\Core\Suite;
use Peridot\Reporter\AnonymousReporter;
use Peridot\Reporter\ReporterInterface;
use Peridot\Runner\Runner;

describe('AnonymousReporter', function() {

    beforeEach(function() {
        $this->eventEmitter = new EventEmitter();
        $this->output = new Symfony\Component\Console\Output\NullOutput();
    });

    it('should call the init function passed in', function() {
        $output = null;
        $emitter = null;
        new AnonymousReporter(function(ReporterInterface $reporter) use (&$output, &$emitter) {
            $output = $reporter->getOutput();
            $emitter = $reporter->getEventEmitter();
        }, $this->output, $this->eventEmitter);
        assert(
            !is_null($output) && !is_null($emitter),
            'output, and emitter should not be null'
        );
    });

});
