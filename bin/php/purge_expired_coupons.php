#!/usr/bin/env php
<?php

require 'autoload.php';

define("COUPON_CLASS", "discount_coupon");

$cli = eZCLI::instance();

$scriptSettings = array();
$scriptSettings['description']    = 'Purges all expired coupons.';
$scriptSettings['use-session']    = true;
$scriptSettings['use-modules']    = true;
$scriptSettings['use-extensions'] = true;

$script = eZScript::instance( $scriptSettings );

$options = $script->getOptions('', '', false, false, array( 'user' => true ));

$script->startup();
$script->initialize();

$arguments = $options['arguments'];
if (count($arguments) < 1) {
    $cli->error("Usage: php extension/discount_coupons/bin/php/purge_expired_coupons.php <CouponFolderUrlAlias>");
    $script->shutdown(1);
}

$parentNodeUrlAlias = $arguments[0];
$parentNodeId = eZURLAliasML::fetchNodeIDByPath($parentNodeUrlAlias);
if (!$parentNodeId) {
    $cli->error("Coupon Folder URL alias could not be found in content tree. Aborting.");
    $script->shutdown(1);
}

$expiryDateTime = time();

// find all discount coupons with an expiry date in the past.
$params =
    array(
            'AttributeFilter' =>  array('and', array('discount_coupon/end_date', '<', $expiryDateTime)),
            'Depth' => 10
    );
$expiredCoupons = eZContentObjectTreeNode::subTreeByNodeID($params, $parentNodeId);
$expiredCouponCount = count($expiredCoupons);

$cli->output("Found $expiredCouponCount expired coupon(s).");

/** @var eZContentObjectTreeNode $expiredCouponNode */
foreach ($expiredCoupons as $expiredCouponNode) {

    if ($expiredCouponNode->classIdentifier() === COUPON_CLASS) {
        $id = $expiredCouponNode->object()->ID;
        $nodeId = $expiredCouponNode->NodeID;
        $dm = $expiredCouponNode->dataMap();
        $couponExpiryAttribute = $dm['end_date']->content();
        $couponExpiryDateTime = $couponExpiryAttribute->attribute('timestamp');
        $cli->output("Coupon: $id. Expired on " . date('Y-m-d', $couponExpiryDateTime) );

        eZContentOperationCollection::deleteObject(array($nodeId), false);
    }
}

$script->shutdown( 0 );

?>