#!/usr/bin/env php
<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    26 Feb 2012
 **/

ini_set( 'memory_limit', '512M' );

require 'autoload.php';

$cli = eZCLI::instance();
$cli->setUseStyles( true );

$scriptSettings = array();
$scriptSettings['description']    = 'Imports old coupons to new ones';
$scriptSettings['use-session']    = true;
$scriptSettings['use-modules']    = true;
$scriptSettings['use-extensions'] = true;

$script = eZScript::instance( $scriptSettings );
$script->startup();
$script->initialize();

$ini           = eZINI::instance();
$userCreatorID = $ini->variable( 'UserSettings', 'UserCreatorID' );
$user          = eZUser::fetch( $userCreatorID );
if( ( $user instanceof eZUser ) === false ) {
    $cli->error(
		'Cannot get user object by userID = "' . $userCreatorID .
		'". ( See site.ini [UserSettings].UserCreatorID )'
	);
    $script->shutdown( 1 );
}
eZUser::setCurrentlyLoggedInUser( $user, $userCreatorID );

$params = array(
	'Depth'            => false,
	'ClassFilterType'  => 'include',
	'ClassFilterArray' => array( 'coupon' ),
	'LoadDataMap'      => false,
	'AsObject'         => true,
	'IgnoreVisibility' => true
);
$oldCoupons     = eZContentObjectTreeNode::subTreeByNodeID( $params, 1 );
$oldCouponsData = array();
foreach( $oldCoupons as $node ) {
	$dataMap = $node->attribute( 'data_map' );
	$coupon  = $dataMap['coupon']->attribute( 'content' );
	$info    = array(
		'name'           => $dataMap['name']->attribute( 'content' ),
		'description'    => trim( strip_tags( $dataMap['description']->toString() ) ),
		'code'           => $coupon['code'],
		'discount_value' => $coupon['discount'],
		'discount_type'  => (int) $coupon['discount_type'] === ezCouponType::DISCOUNT_TYPE_FLAT ? 'Flat' : 'Percent',
		'start_date'     => $coupon['from']->attribute( 'timestamp' ),
		'end_date'       => $coupon['till']->attribute( 'timestamp' ),
	);

	if( isset( $oldCouponsData[ $info['code'] ] ) === false ) {
		$oldCouponsData[ $info['code'] ] = $info;
	}
}

$counter    = array(
	'created' => 0,
	'skipped' => 0
);
$parentNode = eZContentObjectTreeNode::fetchByURLPath( 'new_coupons' );
foreach( $oldCouponsData as $couponData ) {
	$remoteID = 'discount_coupon_' . $couponData['code'];
	if( eZContentObject::fetchByRemoteID( $remoteID ) instanceof eZContentObject ) {
		$counter['skipped']++;
		continue;
	}

	$params = array(
	    'remote_id'        => $remoteID,
	    'class_identifier' => 'discount_coupon',
	    'parent_node_id'   => $parentNode->attribute( 'node_id' ),
	    'attributes'       => $couponData
	);
	$object = eZContentFunctions::createAndPublishObject( $params );
	$counter['created']++;
}

$cli->output( 'Processed old coupons ' . count( $oldCouponsData ) . ' of ' . count( $oldCoupons ) );
$cli->output( 'Created new coupons: ' . $counter['created'] );
$cli->output( 'Skipped new coupons: ' . $counter['skipped'] );

$script->shutdown( 0 );
?>