<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    22 Feb 2013
 **/

class DiscountCouponsType extends eZWorkflowEventType
{
	const TYPE_ID            = 'discountcoupons';
	const STATE_NO_INPUT     = 0;
	const STATE_VALID_CODE   = 1;
	const STATE_CANCEL       = 2;
	const STATE_INVALID_CODE = 3;
	const TYPE_FLAT          = 0;
	const TYPE_PERCENT       = 1;

	public function __construct() {
		$this->eZWorkflowEventType( self::TYPE_ID, 'Discount Coupons' );
		$this->setTriggerTypes(
			array(
				'shop'            => array(
					'confirmorder' => array(
						'before'
					)
				),
				'recurringorders' => array(
					'checkout' => array(
						'before'
					)
				)
			)
		);
	}

	public function execute( $process, $event ) {
		$http  = eZHTTPTool::instance();
		$state = $this->fetchInput( $http, null, $event, $process );

		if( $state == self::STATE_CANCEL ) {
			return eZWorkflowEventType::STATUS_ACCEPTED;
		}

		if( $state != self::STATE_VALID_CODE ) {
			$process->Template = array();
			$process->Template['templateName'] = 'design:workflow/discount_coupon.tpl';
			$process->Template['templateVars'] = array(
				'process' => $process,
				'event'   => $event,
				'state'   => $state
			);
			return eZWorkflowType::STATUS_FETCH_TEMPLATE_REPEAT;
		}

		$parameters = $process->attribute( 'parameter_list' );
		$coupon     = DiscountCouponsHelper::fetchByCode( $event->attribute( 'data_text1' ) );
		$order      = eZOrder::fetch( $parameters['order_id'] );

		// Remove any existing order coupon before appending a new item
		$list = eZOrderItem::fetchListByType( $parameters['order_id'], 'coupon' );
		if( count( $list ) > 0 ) {
			foreach( $list as $item ) {
				$item->remove();
			}
		}

		$discountAmount = 0;
		if(
			$order instanceof eZOrder
			&& $coupon instanceof eZContentObject
		) {
			$discountAmount = self::getProductsDiscountAmount( $order, $coupon );
		}
		if( $discountAmount > 0 ) {
			$usage = new CouponUsage(
				array(
					'coupon_object_id' => $coupon->attribute( 'id' ),
					'order_id'         => $parameters['order_id']
				)
			);
			$usage->store();

			$orderItem = new eZOrderItem( array(
				'order_id'        => $parameters['order_id'],
				'description'     => 'Discount',
				'price'           => round( $discountAmount, 2 ) * -1,
				'type'            => 'coupon',
				'vat_is_included' => true,
				'vat_type_id'     => 1
			) );
			$orderItem->store();
		} else {
			$process->Template = array();
			$process->Template['templateName'] = 'design:workflow/discount_coupon_not_applicable.tpl';
			$process->Template['templateVars'] = array(
				'process' => $process,
				'event'   => $event
			);
			return eZWorkflowType::STATUS_FETCH_TEMPLATE_REPEAT;
		}

		return eZWorkflowType::STATUS_ACCEPTED;
	}

	public function fetchInput( &$http, $base, &$event, &$process ) {
		$var    = 'Code_' . $event->attribute( 'id' );
		$cancel = 'CancelButton_' . $event->attribute( 'id' );
		$select = 'SelectButton_' . $event->attribute( 'id' );

		if(
			$http->hasPostVariable( $cancel )
			&& $http->postVariable( $cancel )
		) {
			return self::STATE_CANCEL;
		}
		if(
			$http->hasPostVariable( $var )
			&& $http->hasPostVariable( $select )
			&& strlen( $http->postVariable( $var ) ) > 0
		) {
			$code = $http->postVariable( $var );
			$event->setAttribute( 'data_text1', $code );

			$coupon = DiscountCouponsHelper::fetchByCode( $code );
			if( DiscountCouponsHelper::isValid( $coupon ) ) {
				return self::STATE_VALID_CODE;
			} else {
				return self::STATE_INVALID_CODE;
			}
		}

		$parameters = $process->attribute( 'parameter_list' );
		$order      = eZOrder::fetch( $parameters['order_id'] );
		if( $order instanceof eZOrder ) {
			$xml = new SimpleXMLElement( $order->attribute( 'data_text_1' ) );
			if( $xml != null ) {
				$code = (string) $xml->coupon_code;
				if( strlen( $code ) == 0 ) {
					return self::STATE_CANCEL;
				}

				$coupon = DiscountCouponsHelper::fetchByCode( $code );
				$event->setAttribute( 'data_text1', $code );
				if( DiscountCouponsHelper::isValid( $coupon ) ) {
					return self::STATE_VALID_CODE;
				} else {
					return self::STATE_INVALID_CODE;
				}
			}
		}

		return self::STATE_NO_INPUT;
	}

	private static function getProductsDiscountAmount( eZOrder $order, eZContentObject $coupon ) {
		$discountableAmount = 10;

		$dataMap  = $coupon->attribute( 'data_map' );
		$discount = $dataMap['discount_value']->attribute( 'content' );
		$type     = $dataMap['discount_type']->attribute( 'content' );
		$type     = (int) $type[0];
		if( $type === self::TYPE_FLAT ) {
			$discountableAmount = min( $discountableAmount, $discount );
		} elseif( $type === self::TYPE_PERCENT ) {
			$discountableAmount = $discountableAmount * ( $discount / 100 );
		}
		$discountableAmount = round( $discountableAmount, 2 );
		return $discountableAmount;
	}
}

eZWorkflowEventType::registerEventType( DiscountCouponsType::TYPE_ID, 'DiscountCouponsType' );
