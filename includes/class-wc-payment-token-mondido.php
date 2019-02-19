<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Payment_Token_Mondido extends WC_Payment_Token_CC {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'Mondido';

	/**
	 * Stores Credit Card payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'last4'         => '',
		'expiry_year'   => '',
		'expiry_month'  => '',
		'card_type'     => '',
		'card_id'       => '',
		'masked_number' => '',
		'card_holder'   => '',
	);

	/**
	 * Validate credit card payment tokens.
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_card_id( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_masked_number( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hook prefix
	 * @return string
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_mondido_get_';
	}

	/**
	 * Returns Card ID
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_card_id( $context = 'view' ) {
		return $this->get_prop( 'card_id', $context );
	}

	/**
	 * Set Card ID
	 *
	 * @param string $card_id
	 */
	public function set_card_id( $card_id ) {
		$this->set_prop( 'card_id', $card_id );
	}

	/**
	 * Returns Masked Number
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_masked_number( $context = 'view' ) {
		return $this->get_prop( 'masked_number', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $masked_number Masked Number
	 */
	public function set_masked_number( $masked_number ) {
		$this->set_prop( 'masked_number', $masked_number );
	}

	/**
	 * Returns Masked Number
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_card_holder( $context = 'view' ) {
		return $this->get_prop( 'card_holder', $context );
	}

	/**
	 * Set Card Holder
	 *
	 * @param string $card_holder
	 */
	public function set_card_holder( $card_holder ) {
		$this->set_prop( 'card_holder', $card_holder );
	}
}
