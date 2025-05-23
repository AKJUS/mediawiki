<?php
namespace MediaWiki\PoolCounter;

use Psr\Log\LoggerInterface;
use Wikimedia\Telemetry\TracerInterface;

/**
 * @since 1.40
 */
class PoolCounterFactory {
	private ?PoolCounterConnectionManager $manager = null;
	private ?array $typeConfigs;
	private array $clientConf;
	private LoggerInterface $logger;
	private TracerInterface $tracer;

	/**
	 * @internal For use by ServiceWiring
	 * @param array|null $typeConfigs See $wgPoolCounterConf
	 * @param array $clientConf See $wgPoolCountClientConf
	 * @param LoggerInterface $logger
	 */
	public function __construct( ?array $typeConfigs, array $clientConf,
		LoggerInterface $logger, TracerInterface $tracer ) {
		$this->typeConfigs = $typeConfigs;
		$this->clientConf = $clientConf;
		$this->logger = $logger;
		$this->tracer = $tracer;
	}

	private function getClientManager(): PoolCounterConnectionManager {
		$this->manager ??= new PoolCounterConnectionManager( $this->clientConf );
		return $this->manager;
	}

	/**
	 * Get a PoolCounter.
	 *
	 * @internal This should only be called from PoolCounterWork
	 * @param string $type The class of actions to limit concurrency for (task type)
	 * @param string $key
	 * @return PoolCounter
	 */
	public function create( string $type, string $key ): PoolCounter {
		$conf = $this->typeConfigs[$type] ?? null;
		if ( $conf === null ) {
			return new PoolCounterNull();
		}

		$class = $conf['class'] ?? null;
		if ( $class === 'PoolCounter_Client' ) {
			// Since 1.16: Introduce PoolCounter_Client in PoolCounter extension.
			// Since 1.40: Move to core as symbolic name, discourage use of class name.
			$class = PoolCounterClient::class;
		}
		/** @var PoolCounter $poolCounter */
		$poolCounter = new $class( $conf, $type, $key );
		$poolCounter->setLogger( $this->logger );
		$poolCounter->setTracer( $this->tracer );

		// Support subclass for back-compat with the extension
		if ( $poolCounter instanceof PoolCounterClient ) {
			$poolCounter->setManager( $this->getClientManager() );
		}

		return $poolCounter;
	}
}
