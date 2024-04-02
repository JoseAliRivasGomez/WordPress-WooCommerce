<?php
/**
 * The PayPalSubscriptions module.
 *
 * @package WooCommerce\PayPalCommerce\PayPalSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use ActionScheduler_Store;
use WC_Order;
use WC_Product;
use WC_Product_Subscription;
use WC_Product_Subscription_Variation;
use WC_Product_Variable;
use WC_Product_Variable_Subscription;
use WC_Subscription;
use WC_Subscriptions_Product;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WP_Post;

/**
 * Class SavedPaymentCheckerModule
 */
class PayPalSubscriptionsModule implements ModuleInterface {

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
			'save_post',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $product_id ) use ( $c ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				try {
					$subscriptions_mode = $settings->get( 'subscriptions_mode' );
				} catch ( NotFoundException $exception ) {
					return;
				}

				$nonce = wc_clean( wp_unslash( $_POST['_wcsnonce'] ?? '' ) );
				if (
					$subscriptions_mode !== 'subscriptions_api'
					|| ! is_string( $nonce )
					|| ! wp_verify_nonce( $nonce, 'wcs_subscription_meta' ) ) {
					return;
				}

				$product = wc_get_product( $product_id );
				if ( ! is_a( $product, WC_Product::class ) ) {
					return;
				}

				$subscriptions_api_handler = $c->get( 'paypal-subscriptions.api-handler' );
				assert( $subscriptions_api_handler instanceof SubscriptionsApiHandler );
				$this->update_subscription_product_meta( $product, $subscriptions_api_handler );
			},
			12
		);

		add_action(
			'woocommerce_save_product_variation',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $variation_id ) use ( $c ) {
				$wcsnonce_save_variations = wc_clean( wp_unslash( $_POST['_wcsnonce_save_variations'] ?? '' ) );

				$subscriptions_helper = $c->get( 'wc-subscriptions.helper' );
				assert( $subscriptions_helper instanceof SubscriptionHelper );

				if (
					! $subscriptions_helper->plugin_is_active()
					|| ! WC_Subscriptions_Product::is_subscription( $variation_id )
					|| ! is_string( $wcsnonce_save_variations )
					|| ! wp_verify_nonce( $wcsnonce_save_variations, 'wcs_subscription_variations' )
				) {
					return;
				}

				$product = wc_get_product( $variation_id );
				if ( ! is_a( $product, WC_Product_Subscription_Variation::class ) ) {
					return;
				}

				$subscriptions_api_handler = $c->get( 'paypal-subscriptions.api-handler' );
				assert( $subscriptions_api_handler instanceof SubscriptionsApiHandler );
				$this->update_subscription_product_meta( $product, $subscriptions_api_handler );
			},
			30
		);

		add_action(
			'woocommerce_process_shop_subscription_meta',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $id, $post ) use ( $c ) {
				$subscription = wcs_get_subscription( $id );
				if ( ! is_a( $subscription, WC_Subscription::class ) ) {
					return;
				}
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( ! $subscription_id ) {
					return;
				}
				$subscriptions_endpoint = $c->get( 'api.endpoint.billing-subscriptions' );
				assert( $subscriptions_endpoint instanceof BillingSubscriptions );

				if ( $subscription->get_status() === 'cancelled' ) {
					try {
						$subscriptions_endpoint->cancel( $subscription_id );
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not cancel subscription product on PayPal. ' . $error );
					}
				}

				if ( $subscription->get_status() === 'pending-cancel' ) {
					try {
						$subscriptions_endpoint->suspend( $subscription_id );
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not suspend subscription product on PayPal. ' . $error );
					}
				}

				if ( $subscription->get_status() === 'active' ) {
					try {
						$current_subscription = $subscriptions_endpoint->subscription( $subscription_id );
						if ( $current_subscription->status === 'SUSPENDED' ) {
							$subscriptions_endpoint->activate( $subscription_id );
						}
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not reactivate subscription product on PayPal. ' . $error );
					}
				}
			},
			20,
			2
		);

		add_filter(
			'woocommerce_order_actions',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $actions, $subscription = null ): array {
				if ( ! is_array( $actions ) || ! is_a( $subscription, WC_Subscription::class ) ) {
					return $actions;
				}

				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id && isset( $actions['wcs_process_renewal'] ) ) {
					unset( $actions['wcs_process_renewal'] );
				}

				return $actions;
			},
			20,
			2
		);

		add_filter(
			'wcs_view_subscription_actions',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $actions, $subscription ): array {
				if ( ! is_a( $subscription, WC_Subscription::class ) ) {
					return $actions;
				}

				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id && $subscription->get_status() === 'active' ) {
					$url = wp_nonce_url(
						add_query_arg(
							array(
								'change_subscription_to'   => 'cancelled',
								'ppcp_cancel_subscription' => $subscription->get_id(),
							)
						),
						'ppcp_cancel_subscription_nonce'
					);

					array_unshift(
						$actions,
						array(
							'url'  => esc_url( $url ),
							'name' => esc_html__( 'Cancel', 'woocommerce-paypal-payments' ),
						)
					);

					$actions['cancel']['name'] = esc_html__( 'Suspend', 'woocommerce-paypal-payments' );
					unset( $actions['subscription_renewal_early'] );
				}

				return $actions;
			},
			11,
			2
		);

		add_action(
			'wp_loaded',
			function() use ( $c ) {
				if ( ! function_exists( 'wcs_get_subscription' ) ) {
					return;
				}

				$cancel_subscription_id = wc_clean( wp_unslash( $_GET['ppcp_cancel_subscription'] ?? '' ) );
				$subscription           = wcs_get_subscription( absint( $cancel_subscription_id ) );
				if ( ! wcs_is_subscription( $subscription ) || $subscription === false ) {
					return;
				}

				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				$nonce           = wc_clean( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
				if ( ! is_string( $nonce ) ) {
					return;
				}

				if (
					$subscription_id
					&& $cancel_subscription_id
					&& $nonce
				) {
					if (
						! wp_verify_nonce( $nonce, 'ppcp_cancel_subscription_nonce' )
						|| ! user_can( get_current_user_id(), 'edit_shop_subscription_status', $subscription->get_id() )
					) {
						return;
					}

					$subscriptions_endpoint = $c->get( 'api.endpoint.billing-subscriptions' );
					$subscription_id        = $subscription->get_meta( 'ppcp_subscription' );
					try {
						$subscriptions_endpoint->cancel( $subscription_id );

						$subscription->update_status( 'cancelled' );
						$subscription->add_order_note( __( 'Subscription cancelled by the subscriber from their account page.', 'woocommerce-paypal-payments' ) );
						wc_add_notice( __( 'Your subscription has been cancelled.', 'woocommerce-paypal-payments' ) );

						wp_safe_redirect( $subscription->get_view_order_url() );
						exit;
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not cancel subscription product on PayPal. ' . $error );
					}
				}
			},
			100
		);

		add_action(
			'woocommerce_subscription_before_actions',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $subscription ) use ( $c ) {
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id ) {
					$environment = $c->get( 'onboarding.environment' );
					$host        = $environment->current_environment_is( Environment::SANDBOX ) ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
					?>
					<tr>
						<td><?php esc_html_e( 'PayPal Subscription', 'woocommerce-paypal-payments' ); ?></td>
						<td>
							<a href="<?php echo esc_url( $host . "/myaccount/autopay/connect/{$subscription_id}" ); ?>" id="ppcp-subscription-id" target="_blank"><?php echo esc_html( $subscription_id ); ?></a>
						</td>
					</tr>
					<?php
				}
			}
		);

		add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $query, $query_vars ): array {
				if ( ! empty( $query_vars['ppcp_subscription'] ) ) {
					$query['meta_query'][] = array(
						'key'   => 'ppcp_subscription',
						'value' => esc_attr( $query_vars['ppcp_subscription'] ),
					);
				}

				return $query;
			},
			10,
			2
		);

		add_action(
			'woocommerce_customer_changed_subscription_to_cancelled',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $subscription ) use ( $c ) {
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id ) {
					$subscriptions_endpoint = $c->get( 'api.endpoint.billing-subscriptions' );
					assert( $subscriptions_endpoint instanceof BillingSubscriptions );

					try {
						$subscriptions_endpoint->suspend( $subscription_id );
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not suspend subscription product on PayPal. ' . $error );
					}
				}
			}
		);

		add_action(
			'woocommerce_customer_changed_subscription_to_active',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $subscription ) use ( $c ) {
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id ) {
					$subscriptions_endpoint = $c->get( 'api.endpoint.billing-subscriptions' );
					assert( $subscriptions_endpoint instanceof BillingSubscriptions );

					try {
						$subscriptions_endpoint->activate( $subscription_id );
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not active subscription product on PayPal. ' . $error );
					}
				}
			}
		);

		add_action(
			'woocommerce_product_options_general_product_data',
			function() use ( $c ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				try {
					$subscriptions_mode = $settings->get( 'subscriptions_mode' );
					if ( $subscriptions_mode === 'subscriptions_api' ) {
						/**
						 * Needed for getting global post object.
						 *
						 * @psalm-suppress InvalidGlobal
						 */
						global $post;
						$product = wc_get_product( $post->ID );
						if ( ! is_a( $product, WC_Product::class ) ) {
							return;
						}

						$environment = $c->get( 'onboarding.environment' );
						echo '<div class="options_group subscription_pricing show_if_subscription hidden">';
						$this->render_paypal_subscription_fields( $product, $environment );
						echo '</div>';

					}
				} catch ( NotFoundException $exception ) {
					return;
				}
			}
		);

		add_action(
			'woocommerce_variation_options_pricing',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $loop, $variation_data, $variation ) use ( $c ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				try {
					$subscriptions_mode = $settings->get( 'subscriptions_mode' );
					if ( $subscriptions_mode === 'subscriptions_api' ) {
						$product = wc_get_product( $variation->ID );
						if ( ! is_a( $product, WC_Product_Subscription_Variation::class ) ) {
							return;
						}

						$environment = $c->get( 'onboarding.environment' );
						$this->render_paypal_subscription_fields( $product, $environment );

					}
				} catch ( NotFoundException $exception ) {
					return;
				}
			},
			10,
			3
		);

		add_action(
			'admin_enqueue_scripts',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $hook ) use ( $c ) {
				if ( ! is_string( $hook ) ) {
					return;
				}
				$settings          = $c->get( 'wcgateway.settings' );
				$subscription_mode = $settings->has( 'subscriptions_mode' ) ? $settings->get( 'subscriptions_mode' ) : '';
				if ( $hook !== 'post.php' || $subscription_mode !== 'subscriptions_api' ) {
					return;
				}

				//phpcs:disable WordPress.Security.NonceVerification.Recommended
				$post_id = wc_clean( wp_unslash( $_GET['post'] ?? '' ) );
				$product = wc_get_product( $post_id );
				if ( ! ( is_a( $product, WC_Product::class ) ) ) {
					return;
				}

				$subscriptions_helper = $c->get( 'wc-subscriptions.helper' );
				assert( $subscriptions_helper instanceof SubscriptionHelper );

				if (
					! $subscriptions_helper->plugin_is_active()
					|| ! (
						is_a( $product, WC_Product_Subscription::class )
						|| is_a( $product, WC_Product_Variable_Subscription::class )
						|| is_a( $product, WC_Product_Subscription_Variation::class )
					)
					|| ! WC_Subscriptions_Product::is_subscription( $product )
				) {
					return;
				}

				$module_url = $c->get( 'paypal-subscriptions.module.url' );
				wp_enqueue_script(
					'ppcp-paypal-subscription',
					untrailingslashit( $module_url ) . '/assets/js/paypal-subscription.js',
					array( 'jquery' ),
					$c->get( 'ppcp.asset-version' ),
					true
				);

				$products = array( $this->set_product_config( $product ) );
				if ( $product->get_type() === 'variable-subscription' ) {
					$products = array();

					/**
					 * Suppress pslam.
					 *
					 * @psalm-suppress TypeDoesNotContainType
					 *
					 * WC_Product_Variable_Subscription extends WC_Product_Variable.
					 */
					assert( $product instanceof WC_Product_Variable );
					$available_variations = $product->get_available_variations();
					foreach ( $available_variations as $variation ) {
						/**
						 * The method is defined in WooCommerce.
						 *
						 * @psalm-suppress UndefinedMethod
						 */
						$variation  = wc_get_product_object( 'variation', $variation['variation_id'] );
						$products[] = $this->set_product_config( $variation );
					}
				}

				wp_localize_script(
					'ppcp-paypal-subscription',
					'PayPalCommerceGatewayPayPalSubscriptionProducts',
					$products
				);
			}
		);

		$endpoint = $c->get( 'paypal-subscriptions.deactivate-plan-endpoint' );
		assert( $endpoint instanceof DeactivatePlanEndpoint );
		add_action(
			'wc_ajax_' . DeactivatePlanEndpoint::ENDPOINT,
			array( $endpoint, 'handle_request' )
		);

		add_action(
			'add_meta_boxes',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( string $post_type, $post_or_order_object ) use ( $c ) {
				if ( ! function_exists( 'wcs_get_subscription' ) ) {
					return;
				}

				$order = ( $post_or_order_object instanceof WP_Post )
					? wc_get_order( $post_or_order_object->ID )
					: $post_or_order_object;

				if ( ! is_a( $order, WC_Order::class ) ) {
					return;
				}

				$subscription = wcs_get_subscription( $order->get_id() );
				if ( ! is_a( $subscription, WC_Subscription::class ) ) {
					return;
				}

				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( ! $subscription_id ) {
					return;
				}

				$screen_id = wc_get_page_screen_id( 'shop_subscription' );
				remove_meta_box( 'woocommerce-subscription-schedule', $screen_id, 'side' );

				$host = $c->get( 'api.paypal-website-url' );

				add_meta_box(
					'ppcp_paypal_subscription',
					__( 'PayPal Subscription', 'woocommerce-paypal-payments' ),
					function() use ( $subscription_id, $host ) {
						$url = trailingslashit( $host ) . 'billing/subscriptions/' . $subscription_id;
						echo '<p>' . esc_html__( 'This subscription is linked to a PayPal Subscription, Cancel it to unlink.', 'woocommerce-paypal-payments' ) . '</p>';
						echo '<p><strong>' . esc_html__( 'Subscription:', 'woocommerce-paypal-payments' ) . '</strong> <a href="' . esc_url( $url ) . '" target="_blank">' . esc_attr( $subscription_id ) . '</a></p>';
					},
					$post_type,
					'side'
				);

			},
			30,
			2
		);

		add_action(
			'action_scheduler_before_execute',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $action_id ) {
				/**
				 * Class exist in WooCommerce.
				 *
				 * @psalm-suppress UndefinedClass
				 */
				$store  = ActionScheduler_Store::instance();
				$action = $store->fetch_action( $action_id );

				$subscription_id = $action->get_args()['subscription_id'] ?? null;
				if ( $subscription_id ) {
					$subscription = wcs_get_subscription( $subscription_id );
					if ( is_a( $subscription, WC_Subscription::class ) ) {
						$paypal_subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
						if ( $paypal_subscription_id ) {
							as_unschedule_action( $action->get_hook(), $action->get_args() );
						}
					}
				}
			}
		);
	}

	/**
	 * Updates subscription product meta.
	 *
	 * @param WC_Product              $product The product.
	 * @param SubscriptionsApiHandler $subscriptions_api_handler The subscription api handler.
	 * @return void
	 */
	private function update_subscription_product_meta( WC_Product $product, SubscriptionsApiHandler $subscriptions_api_handler ): void {
		// phpcs:ignore WordPress.Security.NonceVerification
		$enable_subscription_product = wc_clean( wp_unslash( $_POST['_ppcp_enable_subscription_product'] ?? '' ) );
		$product->update_meta_data( '_ppcp_enable_subscription_product', $enable_subscription_product );
		$product->save();

		if ( ( $product->get_type() === 'subscription' || $product->get_type() === 'subscription_variation' ) && $enable_subscription_product === 'yes' ) {
			if ( $product->meta_exists( 'ppcp_subscription_product' ) && $product->meta_exists( 'ppcp_subscription_plan' ) ) {
				$subscriptions_api_handler->update_product( $product );
				$subscriptions_api_handler->update_plan( $product );
				return;
			}

			if ( ! $product->meta_exists( 'ppcp_subscription_product' ) ) {
				$subscriptions_api_handler->create_product( $product );
			}

			if ( $product->meta_exists( 'ppcp_subscription_product' ) && ! $product->meta_exists( 'ppcp_subscription_plan' ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification
				$subscription_plan_name = wc_clean( wp_unslash( $_POST['_ppcp_subscription_plan_name'] ?? '' ) );
				if ( ! is_string( $subscription_plan_name ) ) {
					return;
				}

				$product->update_meta_data( '_ppcp_subscription_plan_name', $subscription_plan_name );
				$product->save();

				$subscriptions_api_handler->create_plan( $subscription_plan_name, $product );
			}
		}
	}

	/**
	 * Returns subscription product configuration.
	 *
	 * @param WC_Product $product The product.
	 * @return array
	 */
	private function set_product_config( WC_Product $product ): array {
		$plan    = $product->get_meta( 'ppcp_subscription_plan' ) ?? array();
		$plan_id = $plan['id'] ?? '';

		return array(
			'product_connected' => $product->get_meta( '_ppcp_enable_subscription_product' ) ?? '',
			'plan_id'           => $plan_id,
			'product_id'        => $product->get_id(),
			'ajax'              => array(
				'deactivate_plan' => array(
					'endpoint' => \WC_AJAX::get_endpoint( DeactivatePlanEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( DeactivatePlanEndpoint::ENDPOINT ),
				),
			),
		);
	}

	/**
	 * Render PayPal Subscriptions fields.
	 *
	 * @param WC_Product  $product WC Product.
	 * @param Environment $environment The environment.
	 * @return void
	 */
	private function render_paypal_subscription_fields( WC_Product $product, Environment $environment ): void {
		$enable_subscription_product = $product->get_meta( '_ppcp_enable_subscription_product' );
		$style                       = $product->get_type() === 'subscription_variation' ? 'float:left; width:150px;' : '';

		echo '<p class="form-field">';
		echo sprintf(
		// translators: %1$s and %2$s are label open and close tags.
			esc_html__( '%1$sConnect to PayPal%2$s', 'woocommerce-paypal-payments' ),
			'<label for="_ppcp_enable_subscription_product" style="' . esc_attr( $style ) . '">',
			'</label>'
		);
		echo '<input type="checkbox" id="ppcp_enable_subscription_product" name="_ppcp_enable_subscription_product" value="yes" ' . checked( $enable_subscription_product, 'yes', false ) . '/>';
		echo sprintf(
		// translators: %1$s and %2$s are label open and close tags.
			esc_html__( '%1$sConnect Product to PayPal Subscriptions Plan%2$s', 'woocommerce-paypal-payments' ),
			'<span class="description">',
			'</span>'
		);

		echo wc_help_tip( esc_html__( 'Create a subscription product and plan to bill customers at regular intervals. Be aware that certain subscription settings cannot be modified once the PayPal Subscription is linked to this product. Unlink the product to edit disabled fields.', 'woocommerce-paypal-payments' ) );
		echo '</p>';

		$subscription_product   = $product->get_meta( 'ppcp_subscription_product' );
		$subscription_plan      = $product->get_meta( 'ppcp_subscription_plan' );
		$subscription_plan_name = $product->get_meta( '_ppcp_subscription_plan_name' );
		if ( $subscription_product && $subscription_plan ) {
			if ( $enable_subscription_product !== 'yes' ) {
				echo sprintf(
				// translators: %1$s and %2$s are button and wrapper html tags.
					esc_html__( '%1$sUnlink PayPal Subscription Plan%2$s', 'woocommerce-paypal-payments' ),
					'<p class="form-field" id="ppcp-enable-subscription"><label></label><button class="button" id="ppcp-unlink-sub-plan-' . esc_attr( (string) $product->get_id() ) . '">',
					'</button><span class="spinner is-active" id="spinner-unlink-plan" style="float: none; display:none;"></span></p>'
				);
				echo sprintf(
				// translators: %1$s and %2$s is open and closing paragraph tag.
					esc_html__( '%1$sPlan unlinked successfully ✔️%2$s', 'woocommerce-paypal-payments' ),
					'<p class="form-field" id="pcpp-plan-unlinked" style="display: none;">',
					'</p>'
				);
			}

			$host = $environment->current_environment_is( Environment::SANDBOX ) ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
			echo sprintf(
			// translators: %1$s and %2$s are wrapper html tags.
				esc_html__( '%1$sProduct%2$s', 'woocommerce-paypal-payments' ),
				'<p class="form-field" id="pcpp-product"><label style="' . esc_attr( $style ) . '">',
				'</label><a href="' . esc_url( $host . '/billing/plans/products/' . $subscription_product['id'] ) . '" target="_blank">' . esc_attr( $subscription_product['id'] ) . '</a></p>'
			);
			echo sprintf(
			// translators: %1$s and %2$s are wrapper html tags.
				esc_html__( '%1$sPlan%2$s', 'woocommerce-paypal-payments' ),
				'<p class="form-field" id="pcpp-plan"><label style="' . esc_attr( $style ) . '">',
				'</label><a href="' . esc_url( $host . '/billing/plans/' . $subscription_plan['id'] ) . '" target="_blank">' . esc_attr( $subscription_plan['id'] ) . '</a></p>'
			);
		} else {
			echo sprintf(
			// translators: %1$s and %2$s are wrapper html tags.
				esc_html__( '%1$sPlan Name%2$s', 'woocommerce-paypal-payments' ),
				'<p class="form-field"><label for="_ppcp_subscription_plan_name">',
				'</label><input type="text" class="short" id="ppcp_subscription_plan_name" name="_ppcp_subscription_plan_name" value="' . esc_attr( $subscription_plan_name ) . '"></p>'
			);
		}
	}
}
