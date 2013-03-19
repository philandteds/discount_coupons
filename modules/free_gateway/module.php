<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    16 Mar 2013
 **/

$Module = array(
	'name'            => 'Free payment gateway',
 	'variable_params' => true
);

$ViewList = array(
	'redirect' => array(
		'functions' => array( 'pay' ),
		'script'    => 'redirect.php',
		'params'    => array( 'OrderID' )
	),
	'return' => array(
		'functions' => array( 'pay' ),
		'script'    => 'return.php',
		'params'    => array( 'OrderID' )
	)
);

$FunctionList = array(
	'pay' => array()
);
