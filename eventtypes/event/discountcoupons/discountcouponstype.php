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
		$list = array_merge( $list, eZOrderItem::fetchListByType( $parameters['order_id'], 'product_discount' ) );
		if( count( $list ) > 0 ) {
			foreach( $list as $item ) {
				$item->remove();
			}
		}

		$discountProducts = array();
		if(
			$order instanceof eZOrder
			&& $coupon instanceof eZContentObject
		) {
			$discountProducts = array();

			$dataMap = $coupon->attribute( 'data_map' );
			if( (bool) $dataMap['free_shipping']->attribute( 'content' ) ) {
				$shipping         = eZShippingManager::getShippingInfo( $order->attribute( 'productcollection_id' ) );
				$discountProducts = array(
					array(
						'product_id' => 'shipping',
						'discount'   => $shipping['cost']
					)
				);
			}

			$discountProducts = array_merge( $discountProducts, self::getDiscountProducts( $order, $coupon ) );
		}

		if( count( $discountProducts ) > 0 ) {
			$orderItem = new eZOrderItem( array(
				'order_id'        => $parameters['order_id'],
				'description'     => DiscountCouponsHelper::getCouponCode( $coupon ),
				'price'           => 0,
				'type'            => 'coupon',
				'vat_is_included' => true,
				'vat_type_id'     => 1
			) );
			$orderItem->store();
			foreach( $discountProducts as $discountProduct ) {
				$orderItem = new eZOrderItem( array(
					'order_id'        => $parameters['order_id'],
					'description'     => $discountProduct['product_id'],
					'price'           => round( $discountProduct['discount'], 2 ) * -1,
					'type'            => 'product_discount',
					'vat_is_included' => true,
					'vat_type_id'     => 1
				) );
				$orderItem->store();
			}
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

	private static function getDiscountProducts( eZOrder $order, eZContentObject $coupon ) {
		$products = $order->attribute( 'product_items' );
		$products = self::filterProductsByContentClass( $products, $coupon );
		$products = self::filterProductsByAllowedProducts( $products, $coupon );
		$products = self::filterSaleProducts( $products, $coupon );
		$products = self::filterProductsByColour( $products, $coupon );

		$dataMap     = $coupon->attribute( 'data_map' );
		$discount    = $dataMap['discount_value']->attribute( 'content' );
		$type        = $dataMap['discount_type']->attribute( 'content' );
		$type        = (int) $type[0];
		$maxItemQty  = isset( $dataMap['max_item_quantity'] )
			? $dataMap['max_item_quantity']->attribute( 'content' )
			: 0;
		$maxItemQty = strlen( $maxItemQty ) === 0 ? 1 : (int) $maxItemQty; 

		$discountProducts = array();
		if( $type === self::TYPE_PERCENT ) {
			foreach( $products as $product ) {
				if( $maxItemQty > 0 ) {
					$itemQty = min( $product['item_count'], $maxItemQty );
				} else {
					$itemQty = $product['item_count'];
				}
				$discountProducts[] = array(
					'product_id'   => $product['id'],
					'discount'     => round( $itemQty * $product['price_inc_vat'] * ( $discount / 100 ), 2 )
				);
			}
		} elseif( $type === self::TYPE_FLAT ) {
			if( count( $products ) > 0 ) {
				// include shipping info
				$shipping = eZShippingManager::getShippingInfo( $order->attribute( 'productcollection_id' ) );
				$total    = $order->attribute( 'total_inc_vat' ) + $shipping['cost'];

				$discountProducts[] = array(
					'product_id'   => 'all',
					'discount'     => round( min( $total, $discount ), 2 )
				);
			}
		}

		return $discountProducts;
	}

	private static function filterProductsByContentClass( array $products, eZContentObject $coupon ) {
		$productClasses   = array( 'xrow_product', 'sale_bundle' );
		$filteredProducts = array();
		foreach( $products as $product ) {
			$classIdentifier = $product['item_object']->attribute( 'contentobject' )->attribute( 'class_identifier' );
			if( in_array( $classIdentifier, $productClasses ) ) {
				$filteredProducts[] = $product;
			}
		}

		return $filteredProducts;
	}

	private static function filterProductsByAllowedProducts( array $products, eZContentObject $coupon ) {
		$dataMap           = $coupon->attribute( 'data_map' );
		$allowedProducts   = $dataMap['products_and_categories']->attribute( 'content' );
		$allowedProducts   = $allowedProducts['relation_list'];
		$allowedCategories = array();
		foreach( $allowedProducts as $allowedProduct ) {
			if( $allowedProduct['contentclass_identifier'] === 'product_category' ) {
				$allowedCategories[] = eZContentObject::fetch( $allowedProduct['contentobject_id'] );
			}
		}

		$filteredProducts = array();
		foreach( $products as $product ) {
			$isDiscountable = true;
			$object         = $product['item_object']->attribute( 'contentobject' );
			if( count( $allowedProducts ) > 0 ) {
				$isDiscountable = false;

				$nodes = $object->attribute( 'assigned_nodes' );
				foreach( $nodes as $node ) {
					$pathNodeIDs = explode( '/', $node->attribute( 'path_string' ) );
					foreach( $allowedProducts as $allowedProduct ) {
						if( in_array( $allowedProduct['node_id'], $pathNodeIDs ) ) {
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
			$classIdentifier = $product['item_object']->attribute( 'contentobject' )->attribute( 'class_identifier' );
			if( $classIdentifier === 'sale_bundle' ) {
				continue;
			}

			$object  = $product['item_object']->attribute( 'contentobject' );
			$options = $product['item_object']->attribute( 'option_list' );
			if( count( $options ) === 0 ) {
				continue;
			}

			$SKU = $options[0]->attribute( 'value' );
			if( self::isSaleProduct( $object, $SKU ) === false ) {
				$filteredProducts[] = $product;
			}
		}
		return $filteredProducts;
	}

	public static function isSaleProduct( eZContentObject $product, $SKU ) {
		$currentRegion = eZINI::instance( 'shop.ini' )->variable( 'PriceSettings', 'PriceGroup' );

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
			&& (float) $r[0]['Override'] > 0
		) {
			return true;
		}

		return false;
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
			$colour = false;

			$classIdentifier = $product['item_object']->attribute( 'contentobject' )->attribute( 'class_identifier' );
			if( $classIdentifier === 'sale_bundle' ) {
				$variation = PTBasketCheckerHandler::getProductsVariation( $product['item_object'] );
				if( $variation instanceof ProductVariation ) {
					$extraData = $variation->attribute( 'extra_data' );
					if( isset( $extraData['colour'] ) ) {
						$colour = $extraData['colour'];
					}
				}
			} else {
				$options = $product['item_object']->attribute( 'option_list' );
				if( count( $options ) > 0 ) {
					$tmp    = explode( '_', $options[0]->attribute( 'value' ) );
					$colour = $tmp[ count( $tmp ) - 3 ];
				}
			}

			if( in_array( $colour, $allowedColours ) ) {
				$filteredProducts[] = $product;
			}
		}

		return $filteredProducts;
	}

	private static function filterUserChoiseSaleBundles( array $products, eZContentObject $coupon ) {
		$dataMap           = $coupon->attribute( 'data_map' );
		$allowedSizes      = isset($dataMap['product_sizes']) ? $dataMap['product_sizes']->attribute( 'content' ) : '';
		$allowedColours    = $dataMap['product_colours']->attribute( 'content' );
		$allowSaleProducts = (bool) $dataMap['sale_products']->attribute( 'content' );
		$type              = $dataMap['discount_type']->attribute( 'content' );
		$type              = (int) $type[0];

		$allowUserChoiseBundles = strlen( $allowedColours ) === 0
			&& strlen( $allowedSizes ) === 0
			&& $allowSaleProducts
			&& $type === self::TYPE_FLAT;

		$filteredProducts = array();
		foreach( $products as $product ) {
			// We are filtering only user chois bundles and passing the rest
			if( self::isUserChoiceSaleBundle(  $product['item_object']->attribute( 'contentobject' ) ) === false ) {
				$filteredProducts[] = $product;
				continue;
			}

			if( $allowUserChoiseBundles ) {
				$filteredProducts[] = $product;
			}
		}

		return $filteredProducts;
	}

	private static function isProduct( eZContentObject $product ) {
		return $product->attribute( 'class_identifier' ) === 'xrow_product';
	}

	private static function isUserChoiceSaleBundle( eZContentObject $product ) {
		return $product->attribute( 'class_identifier' ) === 'sale_bundle_uc';
	}
}

eZWorkflowEventType::registerEventType( DiscountCouponsType::TYPE_ID, 'DiscountCouponsType' );
