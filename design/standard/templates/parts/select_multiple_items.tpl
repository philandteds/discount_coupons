<div class="select-items">
	<input type="hidden" name="{$attribute_base}_ezstring_data_text_{$attribute.id}" value="{$selected_items|implode( ';' )}" />
	{foreach $possible_items as $item => $name}
		<label>
			<input type="checkbox" value="{$item}"{if $selected_items|contains( $item )} checked="checked"{/if}/>
			{$name}
		</label>
	{/foreach}
</div>
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