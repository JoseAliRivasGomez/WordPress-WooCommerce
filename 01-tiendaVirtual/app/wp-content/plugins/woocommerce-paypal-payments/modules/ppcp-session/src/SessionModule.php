<?php
/**
 * The session module.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class SessionModule
 */
class SessionModule implements ModuleInterface {
	/**
	 * A flag to avoid multiple requests to reload order.
	 *
	 * @var bool
	 */
	private $reloaded_order = false;

	/**
	 * {@inheritDoc}
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): void {
		add_action(
			'woocommerce_init',
			function () use ( $c ) {
				$controller = $c->get( 'session.cancellation.controller' );
				/**
				 * The Cancel controller.
				 *
				 * @var CancelController $controller
				 */
				$controller->run();
			}
		);

		add_action(
			'ppcp_session_get_order',
			function ( ?Order $order, SessionHandler $session_handler ) use ( $c ): void {
				if ( ! isset( WC()->session ) ) {
					return;
				}

				if ( $this->reloaded_order ) {
					return;
				}

				if ( ! $order ) {
					return;
				}

				if ( $order->status()->is( OrderStatus::APPROVED )
					|| $order->status()->is( OrderStatus::COMPLETED )
				) {
					return;
				}

				$order_endpoint = $c->get( 'api.endpoint.order' );
				assert( $order_endpoint instanceof OrderEndpoint );

				$this->reloaded_order = true;

				try {
					$session_handler->replace_order( $order_endpoint->order( $order->id() ) );
				} catch ( Throwable $exception ) {
					$logger = $c->get( 'woocommerce.logger.woocommerce' );
					assert( $logger instanceof LoggerInterface );

					$logger->warning( 'Failed to reload PayPal order in the session: ' . $exception->getMessage() );
				}
			},
			10,
			2
		);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
