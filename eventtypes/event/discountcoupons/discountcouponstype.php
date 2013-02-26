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
		$products = $order->attribute( 'product_items' );
		$products = self::filterProductsByAllowedProducts( $products, $coupon );
		$products = self::filterSaleProducts( $products, $coupon );
		$products = self::filterProductsByRegion( $products, $coupon );
		$products = self::filterProductsByColour( $products, $coupon );
		$products = self::filterProductsBySize( $products, $coupon );

		$discountableAmount = 0;
		foreach( $products as $product ) {
			$discountableAmount += $product['total_price_ex_vat'];
		}

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

	private static function filterProductsByAllowedProducts( array $products, eZContentObject $coupon ) {
		$dataMap           = $coupon->attribute( 'data_map' );
		$allowedProducts   = $dataMap['products_and_categories']->attribute( 'content' );
		$allowedProducts   = $allowedProducts['relation_list'];
		$ageCategories     = eZINI::instance( 'mk.ini' )->variableArray( 'SizeSettings', 'CategorySizes' );
		$allowedCategories = array();
		foreach( $allowedProducts as $allowedProduct ) {
			if( $allowedProduct['contentclass_identifier'] === 'product_category' ) {
				$allowedCategories[] = eZContentObject::fetch( $allowedProduct['contentobject_id'] );
			}
		}

		$allowedSizes = array();
		foreach( $allowedCategories as $category ) {
			$dataMap    = $category->attribute( 'data_map' );
			$identifier = $dataMap['identifier']->attribute( 'content' );
			if( isset( $ageCategories[ $identifier ] ) ) {
				$allowedSizes = array_merge( $allowedSizes, $ageCategories[ $identifier ] );
			}
		}
		$allowedSizes = array_unique( $allowedSizes );

		$filteredProducts = array();
		foreach( $products as $product ) {
			$productSize = null;
			$options     = $product['item_object']->attribute( 'option_list' );
			if( count( $options ) === 0 ) {
				continue;
			}
			$tmp = explode( '_', $options[0]->attribute( 'value' ) );
			if( count( $tmp ) > 2 ) {
				$productSize = $tmp[ count( $tmp ) - 2 ];
			}

			$isDiscountable = true;
			$object = $product['item_object']->attribute( 'contentobject' );
			$SKU    = $options[0]->attribute( 'value' );

			if( count( $allowedProducts ) > 0 ) {
				$isDiscountable = false;

				$nodes = $object->attribute( 'assigned_nodes' );
				foreach( $nodes as $node ) {
					$pathNodeIDs = explode( '/', $node->attribute( 'path_string' ) );
					foreach( $allowedProducts as $allowedProduct ) {
						if(
							in_array( $allowedProduct['node_id'], $pathNodeIDs )
							&& (
								count( $allowedSizes ) === 0
								|| in_array( $productSize, $allowedSizes )
							)
						) {
							$isDiscountable = true;
							break 2;
						}
					}
				}
			}
			if( $isDiscountable ) {
				$filteredProducts[] = $product;
			}
		}

		return $filteredProducts;
	}

	private static function filterSaleProducts( array $products, eZContentObject $coupon ) {
		$dataMap           = $coupon->attribute( 'data_map' );
		$allowSaleProducts = (bool) $dataMap['sale_products']->attribute( 'content' );
		if( $allowSaleProducts ) {
			return $products;
		}

		$filteredProducts = array();
		foreach( $products as $product ) {
			$object  = $product['item_object']->attribute( 'contentobject' );
			$options = $product['item_object']->attribute( 'option_list' );
			if( count( $options ) === 0 ) {
				continue;
			}
			$SKU     = $options[0]->attribute( 'value' );

			if( self::isSaleProduct( $object, $SKU ) === false ) {
				$filteredProducts[] = $product;
			}
		}
		return $filteredProducts;
	}

	public static function isSaleProduct( eZContentObject $product, $SKU ) {
		$dataMap = $product->attribute( 'data_map' );
		if( (bool) $dataMap['override_price']->attribute( 'content' ) ) {
			return true;
		}

		$currentRegion = eZLocale::instance()->LocaleINI['default']->variable( 'RegionalSettings', 'Country' );
		$db = eZDB::instance();
		$q  = '
			SELECT product_price.*
			FROM product_price
			WHERE
				product_price.LongCode = "' . $db->escapeString( $SKU ) . '"
				AND product_price.Region = "' . $db->escapeString( $currentRegion ) . '"';
		$r  = $db->arrayQuery( $q );
		if(
			count( $r ) > 0
			&& (bool) $r[0]['Override']
		) {
			return true;
		}

		return false;
	}

	private static function filterProductsByRegion( array $products, eZContentObject $coupon ) {
		$dataMap        = $coupon->attribute( 'data_map' );
		$allowedRegions = $dataMap['regions']->attribute( 'content' );
		if( strlen( $allowedRegions ) === 0 ) {
			return $products;
		}

		$allowedRegions   = explode( ';', $allowedRegions );
		$filteredProducts = array();
		foreach( $products as $product ) {
			$options = $product['item_object']->attribute( 'option_list' );
			if( count( $options ) === 0 ) {
				continue;
			}
			$tmp = explode( '_', $options[0]->attribute( 'value' ) );
			$productRegion = $tmp[ count( $tmp ) - 1 ];
			if( in_array( $productRegion, $allowedRegions ) ) {
				$filteredProducts[] = $product;
			}
		}

		return $filteredProducts;
	}

	private static function filterProductsByColour( array $products, eZContentObject $coupon ) {
		$dataMap        = $coupon->attribute( 'data_map' );
		$allowedColours = $dataMap['product_colours']->attribute( 'content' );
		if( strlen( $allowedColours ) === 0 ) {
			return $products;
		}

		$allowedColours   = explode( ';', $allowedColours );
		$filteredProducts = array();
		foreach( $products as $product ) {
			$options = $product['item_object']->attribute( 'option_list' );
			if( count( $options ) === 0 ) {
				continue;
			}
			$tmp = explode( '_', $options[0]->attribute( 'value' ) );
			$productColour = $tmp[ count( $tmp ) - 3 ];
			if( in_array( $productColour, $allowedColours ) ) {
				$filteredProducts[] = $product;
			}
		}

		return $filteredProducts;
	}

	private static function filterProductsBySize( array $products, eZContentObject $coupon ) {
		$dataMap      = $coupon->attribute( 'data_map' );
		$allowedSizes = $dataMap['product_sizes']->attribute( 'content' );
		if( strlen( $allowedSizes ) === 0 ) {
			return $products;
		}

		$allowedSizes     = explode( ';', $allowedSizes );
		$filteredProducts = array();
		foreach( $products as $product ) {
			$options = $product['item_object']->attribute( 'option_list' );
			if( count( $options ) === 0 ) {
				continue;
			}
			$tmp = explode( '_', $options[0]->attribute( 'value' ) );
			$productSize = $tmp[ count( $tmp ) - 2 ];
			if( in_array( $productSize, $allowedSizes ) ) {
				$filteredProducts[] = $product;
			}
		}

		return $filteredProducts;
	}
}

eZWorkflowEventType::registerEventType( DiscountCouponsType::TYPE_ID, 'DiscountCouponsType' );
