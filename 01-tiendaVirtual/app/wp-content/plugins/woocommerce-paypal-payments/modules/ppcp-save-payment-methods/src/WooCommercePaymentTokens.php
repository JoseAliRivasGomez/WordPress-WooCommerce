<?php
/**
 * Service to create WC Payment Tokens.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenApplePay;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenVenmo;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class WooCommercePaymentTokens
 */
class WooCommercePaymentTokens {

	/**
	 * The payment token helper.
	 *
	 * @var PaymentTokenHelper
	 */
	private $payment_token_helper;

	/**
	 * The payment token factory.
	 *
	 * @var PaymentTokenFactory
	 */
	private $payment_token_factory;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * WooCommercePaymentTokens constructor.
	 *
	 * @param PaymentTokenHelper  $payment_token_helper The payment token helper.
	 * @param PaymentTokenFactory $payment_token_factory The payment token factory.
	 * @param LoggerInterface     $logger The logger.
	 */
	public function __construct(
		PaymentTokenHelper $payment_token_helper,
		PaymentTokenFactory $payment_token_factory,
		LoggerInterface $logger
	) {
		$this->payment_token_helper  = $payment_token_helper;
		$this->payment_token_factory = $payment_token_factory;
		$this->logger                = $logger;
	}

	/**
	 * Creates a WC Payment Token for PayPal payment.
	 *
	 * @param int    $customer_id    The WC customer ID.
	 * @param string $token          The PayPal payment token.
	 * @param string $email          The PayPal customer email.
	 *
	 * @return int
	 */
	public function create_payment_token_paypal(
		int $customer_id,
		string $token,
		string $email
	): int {

		$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id, PayPalGateway::ID );
		if ( $this->payment_token_helper->token_exist( $wc_tokens, $token, PaymentTokenPayPal::class ) ) {
			return 0;
		}

		// Try to update existing token of type before creating a new one.
		$payment_token_paypal = $this->payment_token_helper->first_token_of_type( $wc_tokens, PaymentTokenPayPal::class );

		if ( ! $payment_token_paypal ) {
			$payment_token_paypal = $this->payment_token_factory->create( 'paypal' );
		}

		assert( $payment_token_paypal instanceof PaymentTokenPayPal );

		$payment_token_paypal->set_token( $token );
		$payment_token_paypal->set_user_id( $customer_id );
		$payment_token_paypal->set_gateway_id( PayPalGateway::ID );

		if ( $email && is_email( $email ) ) {
			$payment_token_paypal->set_email( $email );
		}

		try {
			$payment_token_paypal->save();
		} catch ( Exception $exception ) {
			$this->logger->error(
				"Could not create WC payment token PayPal for customer {$customer_id}. " . $exception->getMessage()
			);
		}

		return $payment_token_paypal->get_id();
	}

	/**
	 * Creates a WC Payment Token for Venmo payment.
	 *
	 * @param int    $customer_id The WC customer ID.
	 * @param string $token       The Venmo payment token.
	 * @param string $email       The Venmo customer email.
	 *
	 * @return int
	 */
	public function create_payment_token_venmo(
		int $customer_id,
		string $token,
		string $email
	): int {

		$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id, PayPalGateway::ID );
		if ( $this->payment_token_helper->token_exist( $wc_tokens, $token, PaymentTokenVenmo::class ) ) {
			return 0;
		}

		// Try to update existing token of type before creating a new one.
		$payment_token_venmo = $this->payment_token_helper->first_token_of_type( $wc_tokens, PaymentTokenVenmo::class );

		if ( ! $payment_token_venmo ) {
			$payment_token_venmo = $this->payment_token_factory->create( 'venmo' );
		}

		assert( $payment_token_venmo instanceof PaymentTokenVenmo );

		$payment_token_venmo->set_token( $token );
		$payment_token_venmo->set_user_id( $customer_id );
		$payment_token_venmo->set_gateway_id( PayPalGateway::ID );

		if ( $email && is_email( $email ) ) {
			$payment_token_venmo->set_email( $email );
		}

		try {
			$payment_token_venmo->save();
		} catch ( Exception $exception ) {
			$this->logger->error(
				"Could not create WC payment token Venmo for customer {$customer_id}. " . $exception->getMessage()
			);
		}

		return $payment_token_venmo->get_id();
	}

	/**
	 * Creates a WC Payment Token for ApplePay payment.
	 *
	 * @param int    $customer_id    The WC customer ID.
	 * @param string $token          The ApplePay payment token.
	 *
	 * @return int
	 */
	public function create_payment_token_applepay(
		int $customer_id,
		string $token
	): int {

		$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id, PayPalGateway::ID );
		if ( $this->payment_token_helper->token_exist( $wc_tokens, $token, PaymentTokenApplePay::class ) ) {
			return 0;
		}

		// Try to update existing token of type before creating a new one.
		$payment_token_applepay = $this->payment_token_helper->first_token_of_type( $wc_tokens, PaymentTokenApplePay::class );

		if ( ! $payment_token_applepay ) {
			$payment_token_applepay = $this->payment_token_factory->create( 'apple_pay' );
		}

		assert( $payment_token_applepay instanceof PaymentTokenApplePay );

		$payment_token_applepay->set_token( $token );
		$payment_token_applepay->set_user_id( $customer_id );
		$payment_token_applepay->set_gateway_id( PayPalGateway::ID );

		try {
			$payment_token_applepay->save();
		} catch ( Exception $exception ) {
			$this->logger->error(
				"Could not create WC payment token ApplePay for customer {$customer_id}. " . $exception->getMessage()
			);
		}

		return $payment_token_applepay->get_id();
	}

	/**
	 * Creates a WC Payment Token for Credit Card payment.
	 *
	 * @param int      $customer_id The WC customer ID.
	 * @param stdClass $payment_token The Credit Card payment token.
	 *
	 * @return int
	 */
	public function create_payment_token_card( int $customer_id, stdClass $payment_token ): int {
		$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id, CreditCardGateway::ID );
		if ( $this->payment_token_helper->token_exist( $wc_tokens, $payment_token->id ) ) {
			return 0;
		}

		$token = new WC_Payment_Token_CC();
		$token->set_token( $payment_token->id );
		$token->set_user_id( get_current_user_id() );
		$token->set_gateway_id( CreditCardGateway::ID );

		$token->set_last4( $payment_token->payment_source->card->last_digits ?? '' );
		$expiry = explode( '-', $payment_token->payment_source->card->expiry ?? '' );
		$token->set_expiry_year( $expiry[0] ?? '' );
		$token->set_expiry_month( $expiry[1] ?? '' );

		$brand = $payment_token->payment_source->card->brand ?? __( 'N/A', 'woocommerce-paypal-payments' );
		if ( $brand ) {
			$token->set_card_type( $brand );
		}

		try {
			$token->save();
		} catch ( Exception $exception ) {
			$this->logger->error(
				"Could not create WC payment token card for customer {$customer_id}. " . $exception->getMessage()
			);
		}

		$token->save();
		return $token->get_id();
	}
}
