<?php
/**
 * @package Shopping
 * @class   FreeRedirectGateway
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    16 Mar 2013
 **/

class FreeRedirectGateway extends eZRedirectGateway
{
	const AUTOMATIC_STATUS = false;
	const TYPE_FREE        = 'free';

	public function __construct() {
		$this->logger = self::getLogHandler();
	}

	public static function getLogHandler() {
		return eZPaymentLogger::CreateForAdd( 'var/log/free_payment_gateway.log' );
	}

	public function createPaymentObject( $processID, $orderID ) {
		$this->logger->writeTimedString( 'FreeRedirectGateway::createPaymentObject' );
        return eZPaymentObject::createNew( $processID, $orderID, self::TYPE_FREE );
	}

	public function createRedirectionUrl( $process ) {
		$this->logger->writeTimedString( 'FreeRedirectGateway::createRedirectionUrl' );

		$processParams = $process->attribute( 'parameter_list' );
		$order = eZOrder::fetch( $processParams['order_id'] );

		$redirectURL = '/free_gateway/redirect/' . $order->attribute( 'id' );
		eZURI::transformURI( $redirectURL, false, 'full' );
		return $redirectURL;
	}

	public static function name() {
		return 'Free Payment Gateway';
	}

	public static function costs() {
		return 0.00;
	}
}
