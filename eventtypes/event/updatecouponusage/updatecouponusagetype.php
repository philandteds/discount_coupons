<?php
/**
 * @package UpdateCouponUsageType
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    14 Mar 2013
 **/

class UpdateCouponUsageType extends eZWorkflowEventType
{
	const TYPE_ID = 'updatecouponusage';

	public function __construct() {
		$this->eZWorkflowEventType( self::TYPE_ID, 'Updates Discount Coupon usage' );
	}

	public function execute( $process, $event ) {
		$parameters = $process->attribute( 'parameter_list' );
		$order      = eZOrder::fetch( $parameters['order_id'] );

		$list = eZOrderItem::fetchListByType( $parameters['order_id'], 'coupon' );
		if( count( $list ) === 0 ) {
			return eZWorkflowType::STATUS_ACCEPTED;
		}

		$code   = $list[0]->attribute( 'description' );
		$coupon = DiscountCouponsHelper::fetchByCode( $code );
		if( $coupon instanceof eZContentObject ) {
			$usage = new CouponUsage(
				array(
					'coupon_object_id' => $coupon->attribute( 'id' ),
					'order_id'         => $parameters['order_id']
				)
			);
			$usage->store();
		}

		return eZWorkflowType::STATUS_ACCEPTED;
	}
}

eZWorkflowEventType::registerEventType( UpdateCouponUsageType::TYPE_ID, 'UpdateCouponUsageType' );
