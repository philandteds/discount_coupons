<div class="maincontentheader">
	<h1>{'Discount coupon'|i18n( 'extension/discount_coupons' )}</h1>
</div>
<p>{'Coupon is not applicable for selected items'|i18n( 'extension/discount_coupons' )}</p>
<form method="post" action="{'shop/confirmorder'|ezurl( 'no' )}">
	<div class="buttonblock">
		<input class="button" type="submit" name="CancelButton_{$event.id}"  value="{'Continue'|i18n( 'extension/discount_coupons' )}" />
	</div>
</form>