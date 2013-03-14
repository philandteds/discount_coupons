{def $checked = false()}
<div class="select-items">
	<input type="hidden" name="{$attribute_base}_ezstring_data_text_{$attribute.id}" value="{$selected_items|implode( ';' )}" />
	{foreach $possible_items as $item => $name}
		<label>
			{set $checked = false()}
			{if ne( $attribute.content, '', )}
				{foreach $selected_items as $selected_item}
					{if and(
						eq( $selected_item, $item ),
						eq( $selected_item|count_chars(), $item|count_chars() )
					)}
						{set $checked = true()}
						{break}
					{/if}
				{/foreach}
			{/if}
			<input type="checkbox" value="{$item}"{if $checked} checked="checked"{/if}/>
			{$name}
		</label>
	{/foreach}
</div>
{undef $checked}
{run-once}
{literal}
<script type="text/javascript">
jQuery( function() {
	jQuery( 'div.select-items input[type="checkbox"]' ).bind( 'change', function( e ) {
		e.preventDefault();
		var selected = new Array();
		var wrapper  = jQuery( this ).parent().parent();
		jQuery( 'input[type="checkbox"]:checked', wrapper ).each( function( i, el ) {
			selected.push( jQuery( el ).val() );
		} );
		jQuery( 'input[type="hidden"]', wrapper ).val( selected.join( ';' ) );
	} );
} );
</script>
{/literal}
{/run-once}