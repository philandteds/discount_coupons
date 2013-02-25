{def $usages = fetch(
	'discount_coupons', 'get_usages', hash(
		'coupon_object_id', $attribute.object.id
	)
)}
{count( $usages )}/{if eq( $attribute.data_int, 0 )}{'Unlimited'|i18n( 'extension/discount_coupon' )}{else}{$attribute.data_int}{/if}
{undef $usages}