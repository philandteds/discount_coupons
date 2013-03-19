<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    16 Mar 2013
 **/

eZPaymentGatewayType::registerGateway(
	FreeRedirectGateway::TYPE_FREE,
	'FreeRedirectGateway',
	'Free Payment Gateway'
);
