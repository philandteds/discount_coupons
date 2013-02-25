<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    20 Feb 2013
 **/

class CouponUsage extends eZPersistentObject
{
	private $cache = array(
		'order' => null
	);

	public function __construct( $row = array() ) {
		$this->eZPersistentObject( $row );

		if( $this->attribute( 'created' ) === null ) {
			$this->setAttribute( 'created', time() );
		}
	}

	public static function definition() {
		return array(
			'fields'              => array(
				'id' => array(
					'name'     => 'id',
					'datatype' => 'integer',
					'default'  => 0,
					'required' => true
				),
				'coupon_object_id' => array(
					'name'     => 'couponObjectID',
					'datatype' => 'integer',
					'default'  => null,
					'required' => true
				),
				'order_id' => array(
					'name'     => 'orderID',
					'datatype' => 'integer',
					'default'  => null,
					'required' => true
				),
				'created' => array(
					'name'     => 'created',
					'datatype' => 'integer',
					'default'  => time(),
					'required' => true
				)
			),
			'function_attributes' => array(
				'order' => 'getOrder'
			),
			'keys'                => array( 'id' ),
			'sort'                => array( 'id' => 'desc' ),
			'increment_key'       => 'id',
			'class_name'          => __CLASS__,
			'name'                => 'discount_coupon_usages'
		);
	}

	public function getOrder() {
		if(
			$this->cache['order'] === null
			&& $this->attribute( 'order_id' ) !== null
		) {
			$order = eZOrder::fetch( $this->attribute( 'order_id' ) );
			if( $order instanceof eZOrder ) {
				$this->cache['order'] = $order;
			}
		}

		return $this->cache['order'];
	}

	public function fetchUsages( $objectID = null ) {
		$conds = null;
		if( $objectID !== null ) {
			$conds = array(
				'coupon_object_id' => $objectID
			);
		}

		return array(
			'result' => eZPersistentObject::fetchObjectList(
				self::definition(),
				null,
				$conds
			)
		);
	}
}
