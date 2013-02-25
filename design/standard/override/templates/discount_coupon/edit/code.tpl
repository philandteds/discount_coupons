{default attribute_base='ContentObjectAttribute'
         html_class='full'}
<div>
	<input id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}" class="{eq( $html_class, 'half' )|choose( 'box', 'halfbox' )} ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" type="text" size="70" name="{$attribute_base}_ezstring_data_text_{$attribute.id}" value="{$attribute.data_text|wash( xhtml )}" />
	<a href="#" class="generate-coupon-code">{'Generate'|i18n( 'extension/discount_coupon' )}</a>
</div>

{literal}
<script type="text/javascript">
jQuery( function() {
	var generate = function( size ) {
	    var text     = '';
	    var possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

	    for( var i=0; i < size; i++ ) {
	        text += possible.charAt( Math.floor( Math.random() * possible.length ) );
     	}

	    return text;
	};
	jQuery( 'a.generate-coupon-code' ).bind( 'click', function( e ) {
		e.preventDefault();
		jQuery( 'input', jQuery( this ).parent() ).val( generate( 16 ) );
	} );
} );
</script>
{/literal}

{/default}

