<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    22 Feb 2013
 **/

class DiscountCouponsHelper
{
	public function fetchRegions() {
		$ini     = eZINI::instance();
		$regions = $ini->variableArray( 'RegionalSettings', 'TranslationSA' );
		$result  = array();
		foreach( $regions as $sa => $region ) {
			$result[ $sa ] = $region[0];
		}
		return array( 'result' => $result );
	}

	public function fetchProductColours() {
		$q = '
			SELECT DISTINCT Colour name, ColourCode item
			FROM product
			WHERE Colour != "" AND ColourCode != ""
			ORDER BY name ASC
		';
		$colours = self::fetchItems( $q );
		asort( $colours );
		return array( 'result' => $colours );
	}

	public function fetchProductSizes() {
		$q = '
			SELECT DISTINCT SUBSTRING_INDEX( SUBSTRING_INDEX( LongCode, \'_\', -2 ), \'_\', 1 ) as item
			FROM product_price
		';
		return array( 'result' => self::fetchItems( $q ) );
	}

	private static function fetchItems( $q ) {
		$items = array();
		$db    = eZDB::instance();
		$r     = $db->arrayQuery( $q );
		foreach( $r as $row ) {
			if( strlen( $row['item'] ) > 0 ) {
				$items[ $row['item'] ] = isset( $row['name'] ) ? $row['name'] : $row['item'];
			}
		}
		return $items;
	}

	public static function fetchByCode( $code ) {
		$params = array(
			'Depth'            => false,
			'ClassFilterType'  => 'include',
			'ClassFilterArray' => array( 'discount_coupon' ),
			'LoadDataMap'      => false,
			'AsObject'         => true,
			'IgnoreVisibility' => true,
			'AttributeFilter'  => array(
				array( 'discount_coupon/code', '=', $code )
			)
		);
		$r = eZContentObjectTreeNode::subTreeByNodeID( $params, 1 );
		if( count( $r ) > 0 ) {
			return $r[0]->attribute( 'object' );
		}
		return false;
	}

	public static function isValid( $coupon ) {
		if( $coupon instanceof eZContentObject === false ) {
			return false;
		}

		$time      = time();
		$dataMap   = $coupon->attribute( 'data_map' );
		$startDate = $dataMap['start_date']->attribute( 'content' );
		$endDate   = $dataMap['end_date']->attribute( 'content' );
		if(
			$startDate->attribute( 'is_valid' )
			&& (int) $startDate->attribute( 'timestamp' ) > $time
		) {
			return false;
		}
		if(
			$endDate->attribute( 'is_valid' )
			&& (int) $endDate->attribute( 'timestamp' ) < $time
		) {
			return false;
		}

		$maxUsage = (int) $dataMap['max_usage_count']->attribute( 'content' );
		if( $maxUsage > 0 ) {
			$usageCheck = CouponUsage::fetchUsages( $coupon->attribute( 'id' ) );
			if( count( $usageCheck['result'] ) >= $maxUsage ) {
				return false;
			}
		}

		if( isset( $dataMap['regions'] ) ) {
			$allowedRegions = $dataMap['regions']->attribute( 'content' );
			if( strlen( $allowedRegions ) > 0 ) {
				$allowedRegions = explode( ';', $allowedRegions );
				if( in_array( $GLOBALS['eZCurrentAccess']['name'], $allowedRegions ) === false ) {
					return false;
				}
			}
		}

		return true;
	}

	public static function getCouponCode( $coupon ) {
		if( $coupon instanceof eZContentObject === false ) {
			return false;
		}
		$dataMap = $coupon->attribute( 'data_map' );
		return $dataMap['code']->attribute( 'content' );
	}
}
