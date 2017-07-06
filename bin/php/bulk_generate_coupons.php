#!/usr/bin/env php
<?php

require 'autoload.php';

$cli = eZCLI::instance();

$scriptSettings = array();
$scriptSettings['description']    = 'Bulk generates discount coupons with random codes.';
$scriptSettings['use-session']    = true;
$scriptSettings['use-modules']    = true;
$scriptSettings['use-extensions'] = true;

$script = eZScript::instance( $scriptSettings );

$options = $script->getOptions(
    '[discount-type:][discount-value:][csv-filename:][description:][free-shipping:][start-date:][end-date:]' .
            '[max-usage-count:][max-item-quantity:][sale-products:][products-and-categories:]' .
            '[regions:][product-colours:]',
    '[root-url-alias][number-of-coupons-to-generate][discount-type][discount-value]',
    array(
        'discount-type' => 'FLAT or PERCENT. Required.',
        'discount-value' => 'Either the discount %, or discount $ amount (depending on discount_type). Required.',
        'csv-filename' => 'Save generated discount coupons to the given CSV file.',
        'description' => 'Optional description field for generated coupon.',
        'free-shipping' => '1 = Yes. 0 = No. Default = 0',
        'start-date' => 'yyyy-mm-dd format.',
        'end-date' => 'yyyy-mm-dd format.',
        'max-usage-count' => 'The number of times a discount code may be used - 0 = unlimited. Default is unlimited.',
        'max-item-quantity' => 'The maxiumum quantity of items this discount can apply to, per product.',
        'sale-products' => '1 = Allow the discount to be used with sale items. 0 = Discount only on non-sale items. Default = 0.',
        'products-and-categories' => 'List of product and category contentobject_ids. Delimited by vertical bar. Default = no limitation.',
        'regions' => 'Regions allowed. Default = No limitation.',
        'product-colours' => 'Product colours allowed. Default = no limitation.'
    ),
    false,
    array("user" => true)
);

$script->startup();
$script->initialize();

$arguments = $options['arguments'];
if (count($arguments) < 4) {
    $cli->error("Missing argument. Run with --help for a listing of valid arguments.");
    $script->shutdown(1);
}

$parentNodeUrlAlias = $arguments[0];
$parentNodeId = eZURLAliasML::fetchNodeIDByPath($parentNodeUrlAlias);
if (!$parentNodeId) {
    $cli->error("Parent URL alias could not be found in content tree. Aborting.");
    $script->shutdown(1);
}

$attributes = array('code' => false, 'name' => false);

$numberOfCouponsToGenerate = $arguments[1];
$discountType = $arguments[2];
$discountValue = $arguments[3];

switch(strtoupper($discountType)) {
    case 'FLAT':
        $attributes['discount_type'] = 'Flat'; // check this value
        break;
    case 'PERCENT':
        $attributes['discount_type'] = 'Percent'; // check this value
        break;
    default:
        $cli->error("discount_type must be either FLAT or PERCENT");
        $script->shutdown(1);
}

if (!is_numeric($discountValue)) {
    $cli->error("discount_value must be a number");
    $script->shutdown(1);
}

$attributes['discount_value'] = $discountValue;
$attributes['description'] = $options['description'];
$attributes['free_shipping'] = $options['free-shipping'];
$attributes['start_date'] = strtotime( $options['start-date']);
$attributes['end_date'] = strtotime( $options['end-date']);
$attributes['max_usage_count'] = $options['max-usage-count'];
$attributes['max_item_quantity'] = $options['max-item-quantity'];
$attributes['sale_products'] = $options['sale-products'];
$attributes['products_and_categories'] = $options['products-and-categories'];
$attributes['regions'] = $options['regions'];
$attributes['product_colours'] = $options['product-colours'];

$csvFilename = $options['csv-filename'];

$couponsGenerated = array();

$couponGenerator = new ptDiscountCouponGenerator();

for ($couponCount = 0; $couponCount < $numberOfCouponsToGenerate; $couponCount++) {
    $couponsGenerated[] = $couponGenerator->saveDiscountCoupon($parentNodeId, $attributes);
}

if ($csvFilename) {
    $fp = fopen($csvFilename, "w");

    fputcsv($fp, array_keys($attributes));

    foreach ($couponsGenerated as $line) {
        fputcsv($fp, array_values($line));
    }

    fclose($fp);
}

$script->shutdown( 0 );

?>