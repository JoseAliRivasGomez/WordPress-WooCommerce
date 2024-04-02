<?php
/**
 * The Credit card gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CaptureCardPayment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\Vaulting\VaultedCreditCardHandler;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class CreditCardGateway
 */
class CreditCardGateway extends \WC_Payment_Gateway_CC {

	use ProcessPaymentTrait, GatewaySettingsRendererTrait, TransactionIdHandlingTrait, PaymentsStatusHandlingTrait;

	const ID = 'ppcp-credit-card-gateway';

	/**
	 * The Settings Renderer.
	 *
	 * @var SettingsRenderer
	 */
	protected $settings_renderer;

	/**
	 * The processor for orders.
	 *
	 * @var OrderProcessor
	 */
	protected $order_processor;

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	protected $config;

	/**
	 * The vaulted credit card handler.
	 *
	 * @var VaultedCreditCardHandler
	 */
	protected $vaulted_credit_card_handler;

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

	/**
	 * The refund processor.
	 *
	 * @var RefundProcessor
	 */
	private $refund_processor;

	/**
	 * The state.
	 *
	 * @var State
	 */
	protected $state;

	/**
	 * Service to get transaction url for an order.
	 *
	 * @var TransactionUrlProvider
	 */
	protected $transaction_url_provider;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	private $payment_token_repository;

	/**
	 * The subscription helper.
	 *
	 * @var SubscriptionHelper
	 */
	protected $subscription_helper;

	/**
	 * The payments endpoint
	 *
	 * @var PaymentsEndpoint
	 */
	protected $payments_endpoint;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * Capture card payment.
	 *
	 * @var CaptureCardPayment
	 */
	private $capture_card_payment;

	/**
	 * The prefix.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * CreditCardGateway constructor.
	 *
	 * @param SettingsRenderer         $settings_renderer The Settings Renderer.
	 * @param OrderProcessor           $order_processor The Order processor.
	 * @param ContainerInterface       $config The settings.
	 * @param string                   $module_url The URL to the module.
	 * @param SessionHandler           $session_handler The Session Handler.
	 * @param RefundProcessor          $refund_processor The refund processor.
	 * @param State                    $state The state.
	 * @param TransactionUrlProvider   $transaction_url_provider Service able to provide view transaction url base.
	 * @param SubscriptionHelper       $subscription_helper The subscription helper.
	 * @param PaymentsEndpoint         $payments_endpoint The payments endpoint.
	 * @param VaultedCreditCardHandler $vaulted_credit_card_handler The vaulted credit card handler.
	 * @param Environment              $environment The environment.
	 * @param OrderEndpoint            $order_endpoint The order endpoint.
	 * @param CaptureCardPayment       $capture_card_payment Capture card payment.
	 * @param string                   $prefix The prefix.
	 * @param LoggerInterface          $logger The logger.
	 */
	public function __construct(
		SettingsRenderer $settings_renderer,
		OrderProcessor $order_processor,
		ContainerInterface $config,
		string $module_url,
		SessionHandler $session_handler,
		RefundProcessor $refund_processor,
		State $state,
		TransactionUrlProvider $transaction_url_provider,
		SubscriptionHelper $subscription_helper,
		PaymentsEndpoint $payments_endpoint,
		VaultedCreditCardHandler $vaulted_credit_card_handler,
		Environment $environment,
		OrderEndpoint $order_endpoint,
		CaptureCardPayment $capture_card_payment,
		string $prefix,
		LoggerInterface $logger
	) {
		$this->id                          = self::ID;
		$this->settings_renderer           = $settings_renderer;
		$this->order_processor             = $order_processor;
		$this->config                      = $config;
		$this->module_url                  = $module_url;
		$this->session_handler             = $session_handler;
		$this->refund_processor            = $refund_processor;
		$this->state                       = $state;
		$this->transaction_url_provider    = $transaction_url_provider;
		$this->subscription_helper         = $subscription_helper;
		$this->payments_endpoint           = $payments_endpoint;
		$this->vaulted_credit_card_handler = $vaulted_credit_card_handler;
		$this->environment                 = $environment;
		$this->order_endpoint              = $order_endpoint;
		$this->capture_card_payment        = $capture_card_payment;
		$this->prefix                      = $prefix;
		$this->logger                      = $logger;

		if ( $state->current_state() === State::STATE_ONBOARDED ) {
			$this->supports = array( 'refunds' );
		}
		if ( $this->config->has( 'dcc_enabled' ) && $this->config->get( 'dcc_enabled' ) ) {
			$this->supports = array(
				'refunds',
				'products',
			);

			if ( $this->config->has( 'vault_enabled_dcc' ) && $this->config->get( 'vault_enabled_dcc' ) ) {
				$supports = apply_filters(
					'woocommerce_paypal_payments_credit_card_gateway_vault_supports',
					array(
						'subscriptions',
						'subscription_cancellation',
						'subscription_suspension',
						'subscription_reactivation',
						'subscription_amount_changes',
						'subscription_date_changes',
						'subscription_payment_method_change',
						'subscription_payment_method_change_customer',
						'subscription_payment_method_change_admin',
						'multiple_subscriptions',
					)
				);

				$this->supports = array_merge(
					$this->supports,
					$supports
				);
			}
		}

		$this->method_title       = __(
			'Advanced Card Processing',
			'woocommerce-paypal-payments'
		);
		$this->method_description = __(
			'Accept debit and credit cards, and local payment methods with PayPal’s latest solution.',
			'woocommerce-paypal-payments'
		);
		$this->title              = $this->config->has( 'dcc_gateway_title' ) ?
			$this->config->get( 'dcc_gateway_title' ) : $this->method_title;
		$this->description        = $this->config->has( 'dcc_gateway_description' ) ?
			$this->config->get( 'dcc_gateway_description' ) : $this->method_description;

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'ppcp' => array(
				'type' => 'ppcp',
			),
		);
	}

	/**
	 * Render the credit card fields.
	 */
	public function form() {
		add_action( 'gettext', array( $this, 'replace_credit_card_cvv_label' ), 10, 3 );
		parent::form();
		remove_action( 'gettext', 'replace_credit_card_cvv_label' );
	}

	/**
	 * Replace WooCommerce credit card field label.
	 *
	 * @param string $translation Translated text.
	 * @param string $text Original text to translate.
	 * @param string $domain Text domain.
	 *
	 * @return string Translated field.
	 */
	public function replace_credit_card_cvv_label( string $translation, string $text, string $domain ): string {
		if ( 'woocommerce' !== $domain || 'Card code' !== $text ) {
			return $translation;
		}

		return __( 'CVV', 'woocommerce-paypal-payments' );
	}

	/**
	 * Returns the icons of the gateway.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon = parent::get_icon();

		$icons = $this->config->has( 'card_icons' ) ? (array) $this->config->get( 'card_icons' ) : array();
		if ( empty( $icons ) ) {
			return $icon;
		}

		$title_options = $this->card_labels();
		$images        = array_map(
			function ( string $type ) use ( $title_options ): string {
				$striped_dark = str_replace( '-dark', '', $type );
				return '<img
                 title="' . esc_attr( $title_options[ $striped_dark ] ) . '"
                 src="' . esc_url( $this->module_url ) . 'assets/images/' . esc_attr( $type ) . '.svg"
                 class="ppcp-card-icon"
                > ';
			},
			$icons
		);

		return implode( '', $images );
	}

	/**
	 * Returns an array of credit card names.
	 *
	 * @return array
	 */
	private function card_labels(): array {
		return array(
			'visa'       => _x(
				'Visa',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'mastercard' => _x(
				'Mastercard',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'amex'       => _x(
				'American Express',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'discover'   => _x(
				'Discover',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'jcb'        => _x(
				'JCB',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'elo'        => _x(
				'Elo',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
			'hiper'      => _x(
				'Hiper',
				'Name of credit card',
				'woocommerce-paypal-payments'
			),
		);
	}

	/**
	 * Whether the gateway is available or not.
	 *
	 * @return bool
	 */
	public function is_available() : bool {
		return $this->is_enabled();
	}

	/**
	 * Process payment for a WooCommerce order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, WC_Order::class ) ) {
			return $this->handle_payment_failure(
				null,
				new GatewayGenericException( new Exception( 'WC order was not found.' ) )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$card_payment_token_id = wc_clean( wp_unslash( $_POST['wc-ppcp-credit-card-gateway-payment-token'] ?? '' ) );
		if ( $card_payment_token_id ) {
			$tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id() );
			foreach ( $tokens as $token ) {
				if ( $token->get_id() === (int) $card_payment_token_id ) {
					$custom_id    = $wc_order->get_order_number();
					$invoice_id   = $this->prefix . $wc_order->get_order_number();
					$create_order = $this->capture_card_payment->create_order( $token->get_token(), $custom_id, $invoice_id );

					$order = $this->order_endpoint->order( $create_order->id );
					$wc_order->update_meta_data( PayPalGateway::INTENT_META_KEY, $order->intent() );

					if ( $order->intent() === 'AUTHORIZE' ) {
						$order = $this->order_endpoint->authorize( $order );

						$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false' );

						if ( $this->subscription_helper->has_subscription( $wc_order->get_id() ) ) {
							$wc_order->update_meta_data( '_ppcp_captured_vault_webhook', 'false' );
						}
					}

					$transaction_id = $this->get_paypal_order_transaction_id( $order );
					if ( $transaction_id ) {
						$this->update_transaction_id( $transaction_id, $wc_order );
					}

					$this->handle_new_order_status( $order, $wc_order );

					return $this->handle_payment_success( $wc_order );
				}
			}
		}

		/**
		 * If customer has chosen a saved credit card payment from checkout page.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$saved_credit_card = wc_clean( wp_unslash( $_POST['saved_credit_card'] ?? '' ) );
		if ( $saved_credit_card && is_checkout() ) {
			try {
				$wc_order = $this->vaulted_credit_card_handler->handle_payment(
					$saved_credit_card,
					$wc_order
				);

				return $this->handle_payment_success( $wc_order );

			} catch ( RuntimeException $error ) {
				return $this->handle_payment_failure( $wc_order, $error );
			}
		}

		/**
		 * If customer is changing subscription payment.
		 */
		if (
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			isset( $_POST['woocommerce_change_payment'] )
			&& $this->subscription_helper->has_subscription( $wc_order->get_id() )
			&& $this->subscription_helper->is_subscription_change_payment()
		) {
			$saved_credit_card = wc_clean( wp_unslash( $_POST['wc-ppcp-credit-card-gateway-payment-token'] ?? '' ) );
			if ( ! $saved_credit_card ) {
				$saved_credit_card = wc_clean( wp_unslash( $_POST['saved_credit_card'] ?? '' ) );
				// phpcs:enable WordPress.Security.NonceVerification.Missing
			}

			if ( $saved_credit_card ) {
				$payment_token = WC_Payment_Tokens::get( $saved_credit_card );
				if ( $payment_token ) {
					$wc_order->add_payment_token( $payment_token );
					$wc_order->save();

					return $this->handle_payment_success( $wc_order );
				}
			}

			wc_add_notice( __( 'Could not change payment.', 'woocommerce-paypal-payments' ), 'error' );

			return array(
				'result'       => 'failure',
				'redirect'     => wc_get_checkout_url(),
				'errorMessage' => __( 'Could not change payment.', 'woocommerce-paypal-payments' ),
			);
		}

		/**
		 * If the WC_Order is paid through the approved webhook.
		 */
		//phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['ppcp-resume-order'] ) && $wc_order->has_status( 'processing' ) ) {
			return $this->handle_payment_success( $wc_order );
		}
		//phpcs:enable WordPress.Security.NonceVerification.Recommended

		try {
			$this->order_processor->process( $wc_order );

			do_action( 'woocommerce_paypal_payments_before_handle_payment_success', $wc_order );

			return $this->handle_payment_success( $wc_order );
		} catch ( PayPalApiException $error ) {
			return $this->handle_payment_failure(
				$wc_order,
				new Exception(
					Messages::generic_payment_error_message() . ' ' . $error->getMessage(),
					$error->getCode(),
					$error
				)
			);
		} catch ( Exception $error ) {
			return $this->handle_payment_failure( $wc_order, $error );
		}
	}

	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, \WC_Order::class ) ) {
			return false;
		}
		return $this->refund_processor->process( $order, (float) $amount, (string) $reason );
	}

	/**
	 * Set the class property then call parent function.
	 *
	 * @param \WC_Order $order WC Order to get transaction url for.
	 *
	 * @inheritDoc
	 */
	public function get_transaction_url( $order ): string {
		$this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base( $order );

		return parent::get_transaction_url( $order );
	}

	/**
	 * Initialize settings for WC.
	 *
	 * @return void
	 */
	public function init_settings() {
		parent::init_settings();

		// looks like in some cases WC uses this field instead of get_option.
		$this->enabled = $this->is_enabled() ? 'yes' : '';
	}

	/**
	 * Get the option value for WC.
	 *
	 * @param string $key The option key.
	 * @param mixed  $empty_value Value when empty.
	 * @return mixed
	 */
	public function get_option( $key, $empty_value = null ) {
		if ( 'enabled' === $key ) {
			return $this->is_enabled();
		}

		return parent::get_option( $key, $empty_value );
	}

	/**
	 * Handle update of WC settings.
	 *
	 * @param string $key The option key.
	 * @param string $value The option value.
	 * @return bool was anything saved?
	 */
	public function update_option( $key, $value = '' ) {
		$ret = parent::update_option( $key, $value );

		if ( 'enabled' === $key ) {

			$this->config->set( 'dcc_enabled', 'yes' === $value );
			$this->config->persist();

			return true;
		}

		return $ret;
	}

	/**
	 * Returns if the gateway is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		return $this->config->has( 'dcc_enabled' ) && $this->config->get( 'dcc_enabled' );
	}

	/**
	 * Returns the settings renderer.
	 *
	 * @return SettingsRenderer
	 */
	protected function settings_renderer(): SettingsRenderer {
		return $this->settings_renderer;
	}
}
