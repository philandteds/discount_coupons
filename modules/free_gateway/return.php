<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    16 Mar 2013
 **/

$order = eZOrder::fetch( (int) $Params['OrderID'] );
if( $order instanceof eZOrder === false ) {
	return $Params['Module']->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

$isAllowed = eZINI::instance( 'shopping.ini' )->variable( 'General', 'FreeGatewayEnabled' ) == 'enabled';
if(
	( $order->attribute( 'total_inc_vat' ) > 0 && $isAllowed === false )
	|| (bool) $order->attribute( 'is_temporary' ) !== true
) {
	return $Params['Module']->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

$paymentObject     = eZPaymentObject::fetchByOrderID( $order->attribute( 'id' ) );
$xrowPaymentObject = xrowPaymentObject::fetchByOrderID( $order->attribute( 'id' ) );
if( $xrowPaymentObject instanceof xrowPaymentObject === false ) {
	$accountInfo       = $order->accountInformation();
	$xrowPaymentObject = xrowPaymentObject::createNew(
		$paymentObject instanceof eZPaymentObject ? $paymentObject->attribute( 'workflowprocess_id' ) : 0,
		$order->attribute( 'id' ),
		'FreeRedirectGateway'
	);
} else {
	$xrowPaymentObject->setAttribute( 'payment_string', 'FreeRedirect' );
}
if( $xrowPaymentObject instanceof xrowPaymentObject ) {
	$xrowPaymentObject->approve();
	$xrowPaymentObject->store();
}


$xmlString = $order->attribute( 'data_text_1' );
if( $xmlString !== null ) {
	$doc = new DOMDocument();
	$doc->loadXML( $xmlString );

	$root    = $doc->documentElement;
	$invoice = $doc->createElement(
		xrowECommerce::ACCOUNT_KEY_PAYMENTMETHOD,
		FreeRedirectGateway::TYPE_FREE
	);
	$root->appendChild( $invoice );
	$order->setAttribute( 'data_text_1', $doc->saveXML() );
	$order->store();
}

if( $paymentObject instanceof eZPaymentObject ) {
	$paymentObject->approve();
	$paymentObject->store();
	eZPaymentObject::continueWorkflow( $paymentObject->attribute( 'workflowprocess_id' ) );
}

return $Params['Module']->redirectTo( 'shop/orderview/' . $order->attribute( 'id' ) );
?>
