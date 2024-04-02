<?php
/**
 * Properties of the Save Payment Methods module.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Helper;

/**
 * Class SavePaymentMethodsApplies
 */
class SavePaymentMethodsApplies {

	/**
	 * The matrix which countries and currency combinations can be used for Save Payment Methods.
	 *
	 * @var array
	 */
	private $allowed_country_currency_matrix;

	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * SavePaymentMethodsApplies constructor.
	 *
	 * @param array  $allowed_country_currency_matrix The matrix which countries and currency combinations can be used for Save Payment Methods.
	 * @param string $currency 3-letter currency code of the shop.
	 * @param string $country 2-letter country code of the shop.
	 */
	public function __construct(
		array $allowed_country_currency_matrix,
		string $currency,
		string $country
	) {
		$this->allowed_country_currency_matrix = $allowed_country_currency_matrix;
		$this->currency                        = $currency;
		$this->country                         = $country;
	}

	/**
	 * Returns whether Save Payment Methods can be used in the current country and the current currency used.
	 *
	 * @return bool
	 */
	public function for_country_currency(): bool {
		if ( ! in_array( $this->country, array_keys( $this->allowed_country_currency_matrix ), true ) ) {
			return false;
		}
		return in_array( $this->currency, $this->allowed_country_currency_matrix[ $this->country ], true );
	}
}
