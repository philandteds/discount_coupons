{def
	$product_node = false()
	$path         = array()
	$counter      = 0
}
{if eq( $attribute.content.relation_list|count, 0 )}
	{'Any'|i18n( 'extension/discount_coupon' )}
{else}
	{foreach $attribute.content.relation_list as $item}
		{set $product_node = fetch( 'content', 'node', hash( 'node_id', $item['node_id'] ) )}
		{set $path = array( concat( '<a href="', $product_node.url_alias|ezurl( 'no' ), '">', $product_node.name, '</a>' ) )}
		{if gt( $product_node.parent_node_id, 2 )}
		{do}
			{set $product_node = $product_node.parent}
	    	{set $counter = inc( $counter )}
	    	{set $path    = $path|append( concat( '<a href="', $product_node.url_alias|ezurl( 'no' ), '">', $product_node.name, '</a>' ) )}
		{/do while and( gt( $product_node.parent_node_id, 2 ), lt( $counter, 20 ) )}
		{/if}
		{$path|reverse()|implode( ' > ' )}<br />
	{/foreach}
{/if}
{undef $product_node $path $counter}