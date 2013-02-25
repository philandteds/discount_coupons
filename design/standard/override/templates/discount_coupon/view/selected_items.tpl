{def $selected_items = $attribute.data_text|explode( ';' )}
{if eq( $attribute.data_text, '' )}{'Any'|i18n( 'extension/discount_coupon' )}{else}{$selected_items|implode( ', ' )}{/if}
{undef $selected_items}
