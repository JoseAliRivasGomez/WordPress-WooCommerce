<?php
/**
 * The Card Fields module.
 *
 * @package WooCommerce\PayPalCommerce\CardFields
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\CardFields;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class CardFieldsModule
 */
class CardFieldsModule implements ModuleInterface {

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
		if ( ! $c->get( 'card-fields.eligible' ) ) {
			return;
		}

		$settings = $c->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );
		if ( ! $settings->has( 'dcc_enabled' ) || ! $settings->get( 'dcc_enabled' ) ) {
			return;
		}

		/**
		 * Param types removed to avoid third-party issues.
		 *
		 * @psalm-suppress MissingClosureParamType
		 */
		add_filter(
			'woocommerce_paypal_payments_sdk_components_hook',
			function( $components ) {
				if ( in_array( 'hosted-fields', $components, true ) ) {
					$key = array_search( 'hosted-fields', $components, true );
					if ( $key !== false ) {
						unset( $components[ $key ] );
					}
				}
				$components[] = 'card-fields';

				return $components;
			}
		);

		add_filter(
			'woocommerce_credit_card_form_fields',
			/**
			 * Return/Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureReturnType
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $default_fields, $id ) {
				if ( CreditCardGateway::ID === $id && apply_filters( 'woocommerce_paypal_payments_enable_cardholder_name_field', false ) ) {
					$default_fields['card-name-field'] = '<p class="form-row form-row-wide">
						<label for="ppcp-credit-card-gateway-card-name">' . esc_attr__( 'Cardholder Name', 'woocommerce-paypal-payments' ) . '</label>
						<input id="ppcp-credit-card-gateway-card-name" class="input-text wc-credit-card-form-card-expiry" type="text" placeholder="' . esc_attr__( 'Cardholder Name (optional)', 'woocommerce-paypal-payments' ) . '" name="ppcp-credit-card-gateway-card-name">
					</p>';

					// Moves new item to first position.
					$new_field = $default_fields['card-name-field'];
					unset( $default_fields['card-name-field'] );
					array_unshift( $default_fields, $new_field );
				}

				if ( apply_filters( 'woocommerce_paypal_payments_card_fields_translate_card_number', true ) ) {
					if ( isset( $default_fields['card-number-field'] ) ) {
						// Replaces the default card number placeholder with a translatable one.
						$default_fields['card-number-field'] = str_replace(
							'&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;',
							esc_attr__( 'Card number', 'woocommerce-paypal-payments' ),
							$default_fields['card-number-field']
						);
					}
				}

				return $default_fields;
			},
			10,
			2
		);

		add_filter(
			'ppcp_create_order_request_body_data',
			function( array $data ) use ( $c ): array {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$payment_method = wc_clean( wp_unslash( $_POST['payment_method'] ?? '' ) );
				if ( $payment_method !== CreditCardGateway::ID ) {
					return $data;
				}

				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				if (
				$settings->has( '3d_secure_contingency' )
				&& (
					$settings->get( '3d_secure_contingency' ) === 'SCA_ALWAYS'
					|| $settings->get( '3d_secure_contingency' ) === 'SCA_WHEN_REQUIRED'
				)
				) {
					$data['payment_source']['card'] = array(
						'attributes' => array(
							'verification' => array(
								'method' => $settings->get( '3d_secure_contingency' ),
							),
						),
					);
				}

				return $data;
			}
		);
	}
}
