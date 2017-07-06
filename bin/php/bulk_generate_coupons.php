#!/usr/bin/env php
<?php

require 'autoload.php';

$cli = eZCLI::instance();

$scriptSettings = array();
$scriptSettings['description']    = 'Imports old coupons to new ones';
$scriptSettings['use-session']    = false;
$scriptSettings['use-modules']    = true;
$scriptSettings['use-extensions'] = true;

$script = eZScript::instance( $scriptSettings );

$options = $script->getOptions(
    '[discount_type:][discount_value:][csv_filename:][description:][free_shipping:][start_date:][end_date:]' .
            '[max_usage_count:][max_item_quantity:][sale_products:][products_and_categories:]' .
            '[regions:][product_colours:]', '[root_url_alias][number_of_coupons_to_generate][discount_type][discount_value]', array(
        'discount_type' => 'FLAT or PERCENT. Required.',
        'discount_value' => 'Either the discount %, or discount $ amount (depending on discount_type). Required.',
        'csv_filename' => 'Save generated discount coupons to the given CSV file.',
        'description' => 'Optional description field for generated coupon.',
        'free_shipping' => '1 = Yes. 0 = No.',
        'start_date' => 'yyyy-mm-dd format.',
        'end_date' => 'yyyy-mm-dd format.',
        'max_usage_count' => 'The number of times a discount code may be used - 0 = unlimited.',
        'max_item_quantity' => 'The maxiumum quantity of items this discount can apply to, per product.',
        'sale_products' => '1 = Allow the discount to be used with sale items. 0 = Discount only on non-sale items.',
        'products_and_categories' => 'List of product and category contentobject_ids. Delimited by vertical bar.',
        'regions' => 'Regions allowed. Blank = No limitation.',
        'product_colours' => 'Product colours allowed. Blank = no limitation.'
    )
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
        $attributes['discount_type'] = 0; // check this value
        break;
    case 'PERCENT':
        $attributes['discount_type'] = 1; // check this value
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
$attributes['free_shipping'] = $options['free_shipping'];
$attributes['start_date'] = strtotime('YY-mm-dd', $options['start_date']);
$attributes['end_date'] = strtotime('YY-mm-dd', $options['end_date']);
$attributes['max_usage_count'] = $options['max_usage_count'];
$attributes['max_item_quantity'] = $options['max_item_quantity'];
$attributes['sale_products'] = $options['sale_products'];
$attributes['products_and_categories'] = $options['products_and_categories'];
$attributes['regions'] = $options['regions'];
$attributes['product_colours'] = $options['product_colours'];

$csvFilename = $options['csv_filename'];

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