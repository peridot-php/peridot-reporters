<?php
namespace Peridot\Reporter;

use Evenement\EventEmitterInterface;
use Peridot\Configuration;
use Peridot\Core\HasEventEmitterTrait;
use Peridot\Core\Test;
use Peridot\Core\TestInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * The base class for all Peridot reporters. Sits on top of an OutputInterface
 * and an EventEmitter in order to report Peridot results.
 *
 * @package Peridot\Reporter
 */
abstract class AbstractBaseReporter implements ReporterInterface
{
    use HasEventEmitterTrait;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var int
     */
    protected $passing = 0;

    /**
     * @var int
     */
    protected $pending = 0;

    /**
     * @var double|integer
     */
    protected $time;

    /**
     * @var bool
     */
    protected $colorsEnabled = true;

    /**
     * Maps color names to left and right color sequences.
     *
     * @var array
     */
    protected $colors = array(
        'white' => ['left' => "\033[37m", 'right' => "\033[39m"],
        'success' => ['left' => "\033[32m", 'right' => "\033[39m"],
        'error' => ['left' => "\033[31m", 'right' => "\033[39m"],
        'muted' => ['left' => "\033[90m", 'right' => "\033[0m"],
        'pending' => ['left' => "\033[36m", 'right' => "\033[39m"],
    );

    /**
     * Maps symbol names to symbols
     *
     * @var array
     */
    protected $symbols = array(
        'check' => '✓'
    );

    /**
     * @param OutputInterface $output
     * @param EventEmitterInterface $eventEmitter
     */
    public function __construct(
        OutputInterface $output,
        EventEmitterInterface $eventEmitter
    ) {
        $this->output = $output;
        $this->eventEmitter = $eventEmitter;

        $this->registerSymbols();
        $this->registerEvents();
        $this->init();
    }

    /**
     * Set whether or not colors are enabled in this reporter.
     */
    public function setColorsEnabled($enabled)
    {
        $this->colorsEnabled = (bool) $enabled;
    }

    /**
     * Check if colors are enabled.
     *
     * @return bool
     */
    public function areColorsEnabled()
    {
        return $this->colorsEnabled;
    }

    /**
     * Given a color name, colorize the provided text in that
     * color
     *
     * @param $key
     * @param $text
     * @return string
     */
    public function color($key, $text)
    {
        if (!$this->areColorsEnabled() || !$this->hasColorSupport()) {
            return $text;
        }

        $color = $this->colors[$key];

        return sprintf("%s%s%s", $color['left'], $text, $color['right']);
    }

    /**
     * Set whether colors are enabled.
     *
     * @param bool $enabled
     */
    public function setColorsEnabled($enabled)
    {
        $this->colorsEnabled = (bool) $enabled;
    }

    /**
     * Fetch a symbol by name
     *
     * @param $name
     * @return string
     */
    public function symbol($name)
    {
        return $this->symbols[$name];
    }

    /**
     * Return the OutputInterface associated with the Reporter
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set the run time to report.
     *
     * @param float $time
     */
    public function setTime($time)
    {
        $this->time = $time;
    }

    /**
     * Get the run time to report.
     *
     * @return float
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * Output result footer
     */
    public function footer()
    {
        $this->output->write($this->color('success', sprintf("\n  %d passing", $this->passing)));
        $this->output->writeln(sprintf($this->color('muted', " (%s)"), \PHP_Timer::secondsToTimeString($this->getTime())));
        if (! empty($this->errors)) {
            $this->output->writeln($this->color('error', sprintf("  %d failing", count($this->errors))));
        }
        if ($this->pending) {
            $this->output->writeln($this->color('pending', sprintf("  %d pending", $this->pending)));
        }
        $this->output->writeln("");
        $errorCount = count($this->errors);
        for ($i = 0; $i < $errorCount; $i++) {
            list($test, $error) = $this->errors[$i];
            $this->outputError($i + 1, $test, $error);
        }
    }

    /**
     * Output a test failure.
     *
     * @param int $errorNumber
     * @param TestInterface $test
     * @param $exception - an exception like interface with ->getMessage(), ->getTraceAsString()
     */
    protected function outputError($errorNumber, TestInterface $test, $exception)
    {
        $this->output->writeln(sprintf("  %d)%s:", $errorNumber, $test->getTitle()));

        $message = sprintf("     %s", str_replace(PHP_EOL, PHP_EOL . "     ", $exception->getMessage()));
        $this->output->writeln($this->color('error', $message));

        $trace = preg_replace('/^#/m', "      #", $exception->getTraceAsString());
        $this->output->writeln($this->color('muted', $trace));
    }

    /**
     * Determine if colorized output is supported by the reporters output.
     * Taken from Symfony's console output with some slight modifications
     * to use the reporter's output stream
     *
     * @return bool
     */
    protected function hasColorSupport()
    {
        if ($this->isOnWindows()) {
            return $this->hasAnsiSupport();
        }

        return $this->hasTty();
    }

    /**
     * Register reporter symbols, additionally checking OS compatibility.
     */
    protected function registerSymbols()
    {
        //update symbols for windows
        if ($this->isOnWindows()) {
            $this->symbols['check'] = chr(251);
        }
    }

    /**
     * Return true if reporter is being used on windows
     *
     * @return bool
     */
    protected function isOnWindows()
    {
        return DIRECTORY_SEPARATOR == '\\';
    }

    /**
     * Register events tracking state relevant to all reporters.
     */
    private function registerEvents()
    {
        $this->eventEmitter->on('runner.end', [$this, 'setTime']);

        $this->eventEmitter->on('test.failed', function (Test $test, $e) {
            $this->errors[] = [$test, $e];
        });

        $this->eventEmitter->on('test.passed', function () {
            $this->passing++;
        });

        $this->eventEmitter->on('test.pending', function () {
            $this->pending++;
        });
    }

    /**
     * Determine if the terminal has ansicon support
     *
     * @return bool
     */
    private function hasAnsiSupport()
    {
        return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
    }

    /**
     * Determine if reporter is reporting to a tty terminal.
     *
     * @return bool
     */
    private function hasTty()
    {
        if (! $this->output instanceof StreamOutput) {
            return false;
        }

        if (getenv("PERIDOT_TTY")) {
            return true;
        }

        return $this->isTtyTerminal($this->output);
    }

    /**
     * See if stream output is a tty terminal.
     *
     * @return bool
     */
    private function isTtyTerminal(StreamOutput $output)
    {
        $tty = function_exists('posix_isatty') && @posix_isatty($output->getStream());
        if ($tty) {
            putenv("PERIDOT_TTY=1");
        }
        return $tty;
    }

    /**
     * Initialize reporter. Setup and listen for events
     *
     * @return void
     */
    abstract public function init();
}
