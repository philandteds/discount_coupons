<div class="maincontentheader">
	<h1>{'Discount coupon'|i18n( 'extension/discount_coupons' )}</h1>
</div>
<p>{'Please enter a vaild coupon, if you received one from our service. If you don\'t have any valid coupon, click "No coupon" to skip this step.'|i18n( 'extension/discount_coupons' )}</p>

{if $state|eq( 3 )}
<div class="message-warning">
	<h2>{'Warning'|i18n( 'extension/discount_coupons' )}</h2>
	<p>{'Required data is either missing or is invalid:'|i18n( 'extension/discount_coupons' )}</p>
	<ul>
		<li>{'Invalid or expired coupon code.'|i18n( 'extension/discount_coupons' )}</li>
	</ul>
</div>
{/if}

<form method="post" action="{'shop/confirmorder'|ezurl( 'no' )}">
	<div class="block">
	<div class="element">
		<label>{'Coupon code:'|i18n( 'extension/discount_coupons' )} <input type="input" name="Code_{$event.id}" value="{$event.data_text1}" /></label>
	</div>
</div>

<div class="buttonblock">
	<input class="button" type="submit" name="SelectButton_{$event.id}"  value="{'Have coupon'|i18n( 'extension/discount_coupons' )}" />
	<input class="button" type="submit" name="CancelButton_{$event.id}"  value="{'No coupon'|i18n( 'extension/discount_coupons' )}" />
</div>

</form>
