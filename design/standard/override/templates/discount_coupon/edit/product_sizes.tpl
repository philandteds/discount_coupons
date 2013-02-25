{def
	$possible_items = fetch( 'discount_coupons', 'get_product_sizes' )
	$selected_items = $attribute.data_text|explode( ';' )
}
{include
	uri='design:parts/select_multiple_items.tpl'
	possible_items=$possible_items
	selected_items=$selected_items
	attribute=$attribute
}
{undef $possible_items $selected_items}
