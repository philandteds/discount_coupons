<?php
/**
 * @package DiscountCoupons
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    20 Feb 2013
 **/

class discount_couponsHandler extends eZContentObjectEditHandler
{
	const CONTENT_CLASS = 'discount_coupon';
	const CODE_ATTR     = 'code';

	public function validateInput(
		$http,
		&$module,
		&$class,
		$object,
		&$version,
		$contentObjectAttributes,
		$editVersion,
		$editLanguage,
		$fromLanguage,
		$validationParameters
	) {
		$result = array( 'is_valid' => true, 'warnings' => array() );
		if( $object->attribute( 'class_identifier' ) !== self::CONTENT_CLASS ) {
			return $result;
		}

		$dataMap = $object->attribute( 'data_map' );
		if( isset( $dataMap['code'] ) === false ) {
			return $result;
		}

		$warnings = array();

		$attr   = $dataMap['code'];
		$var    = 'ContentObjectAttribute_ezstring_data_text_' . $attr->attribute( 'id' );
		$code   = $http->hasVariable( $var ) ? trim( $http->variable( $var ) ) : null;
		$dClass = eZContentClass::fetchByIdentifier( self::CONTENT_CLASS, false );
		$attrID = eZContentClassAttribute::classAttributeIDByIdentifier( self::CONTENT_CLASS . '/' . self::CODE_ATTR );
		if( $code === null || $dClass === null || $attrID === false ) {
			return $result;
		}

		$db = eZDB::instance();
		$q  = '
			SELECT * FROM ezcontentobject o
			RIGHT JOIN ezcontentobject_attribute a ON a.contentobject_id = o.id AND a.contentclassattribute_id = ' . $attrID . '
			WHERE
				o.contentclass_id = ' . $dClass['id'] . '
				AND o.id != ' . $object->attribute( 'id' ) . '
				AND a.data_text = "' . $db->escapeString( $code ) . '"
		';
		$r  = $db->arrayQuery( $q );
		if( count( $r ) > 0 ) {
			$warnings['code'] = array(
				'text' => ezpI18n::tr(
					'extension/discount_coupons',
					'Another discount coupon with specified code already exists'
				)
			);
		}

		return array(
			'is_valid' => count( $warnings ) === 0,
			'warnings' => $warnings
		);
	}
}
