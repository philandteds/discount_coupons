<?php

/**
 * Generates and saves a unique discount coupon. Returns it as an associative array.
 */
class ptDiscountCouponGenerator
{
    const DISCOUNT_CODE_LENGTH = 16;

    protected function generateDiscountCode($discountCodeLength = self::DISCOUNT_CODE_LENGTH) {

        $validDiscountCharacters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $validDiscountCharactersCount = strlen($validDiscountCharacters);

        $discountCode = '';
        for ($i = 0; $i < $discountCodeLength; $i++) {
            $discountCode .= $validDiscountCharacters[rand(0, $validDiscountCharactersCount-1)];
        }

        return $discountCode;
    }


    public function saveDiscountCoupon($parentNodeId, $attributes) {

        $discountCode = $this->generateDiscountCode();

        $attributes['name'] = 'Discount coupon ' . $discountCode;
        $attributes['code'] = $discountCode;

        $params = array(
            'class_identifier' => 'discount_coupon',
            'parent_node_id'   => $parentNodeId,
            'attributes'       => $attributes
        );

        $object = eZContentFunctions::createAndPublishObject( $params );

        return $attributes;
    }

}