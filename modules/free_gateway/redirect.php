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

return $Params['Module']->redirectTo( '/free_gateway/return/' . $order->attribute( 'id' ) );
