<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    22 Feb 2013
 **/

$FunctionList = array();
$FunctionList['get_usages'] = array(
	'name'           => 'get_usages',
	'call_method'    => array(
		'class'  => 'CouponUsage',
		'method' => 'fetchUsages'
	),
	'parameter_type' => 'standard',
	'parameters'       => array(
		array(
			'name'     => 'coupon_object_id',
			'type'     => 'integer',
			'required' => false,
			'default'  => false
		)
	)
);
$FunctionList['get_regions'] = array(
	'name'           => 'get_regions',
	'call_method'    => array(
		'class'  => 'DiscountCouponsHelper',
		'method' => 'fetchRegions'
	),
	'parameter_type' => 'standard',
	'parameters'     => array()
);
$FunctionList['get_product_colours'] = array(
	'name'           => 'get_product_colours',
	'call_method'    => array(
		'class'  => 'DiscountCouponsHelper',
		'method' => 'fetchProductColours'
	),
	'parameter_type' => 'standard',
	'parameters'     => array()
);
$FunctionList['get_product_sizes'] = array(
	'name'           => 'get_product_sizes',
	'call_method'    => array(
		'class'  => 'DiscountCouponsHelper',
		'method' => 'fetchProductSizes'
	),
	'parameter_type' => 'standard',
	'parameters'     => array()
);
