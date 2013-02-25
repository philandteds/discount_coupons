DROP TABLE IF EXISTS `discount_coupon_usages`;
CREATE TABLE `discount_coupon_usages` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `coupon_object_id` int(11) unsigned NOT NULL,
  `order_id` int(11) unsigned NOT NULL,
  `created` int(11) unsigned NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
