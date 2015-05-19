<?php
namespace Peridot\Reporter;

use Peridot\EventEmitterInterface;
use Peridot\Core\HasEventEmitterTrait;
use Peridot\Core\Context;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The ReporterFactory is used to list and register Peridot reporters.
 *
 * @package Peridot\Reporter
 */
class ReporterFactory
{
    use HasEventEmitterTrait;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Peridot\Core\Context
     */
    protected $context;

    /**
     * Registered reporters
     *
     * @var array
     */
    protected $reporters = array(
        'spec' => ['description' => 'hierarchical spec list', 'factory' => 'Peridot\Reporter\SpecReporter']
    );

    /**
     * @param OutputInterface $output
     * @param EventEmitterInterface $eventEmitter
     */
    public function __construct(
        OutputInterface $output,
        EventEmitterInterface $eventEmitter,
        Context $context
    ) {
        $this->output = $output;
        $this->eventEmitter = $eventEmitter;
        $this->context = $context;
    }

    /**
     * Return an instance of the named reporter
     *
     * @param $name
     * @return \Peridot\Reporter\AbstractBaseReporter
     */
    public function create($name)
    {
        $factory = $this->getReporterFactory($name);

        $isClass = is_string($factory) && class_exists($factory);

        if ($isClass) {
            return new $factory($this->output, $this->eventEmitter, $this->context);
        }

        if (is_callable($factory)) {
            return new AnonymousReporter($factory, $this->output, $this->eventEmitter, $this->context);
        }

        throw new \RuntimeException("Reporter class could not be created");
    }

    /**
     * Return the factory defined for the named reporter
     *
     * @param string $name
     * @return null|string|callable
     */
    public function getReporterFactory($name)
    {
        $definition = $this->getReporterDefinition($name);
        if (! isset($definition['factory'])) {
            $definition['factory'] = null;
        }
        return $definition['factory'];
    }

    /**
     * Return the definition of the named reporter
     *
     * @param string $name
     * @return array
     */
    public function getReporterDefinition($name)
    {
        $definition = [];
        if (isset($this->reporters[$name])) {
            $definition = $this->reporters[$name];
        }
        return $definition;
    }

    /**
     * Register a named reporter with the factory.
     *
     * @param string $name
     * @param string $description
     * @param string $factory Either a callable or a fully qualified class name
     */
    public function register($name, $description, $factory)
    {
        $this->reporters[$name] = ['description' => $description, 'factory' => $factory];
    }

    /**
     * @return array
     */
    public function getReporters()
    {
        return $this->reporters;
    }
}
