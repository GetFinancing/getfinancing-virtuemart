CREATE TABLE IF NOT EXISTS `#__getfinancing` (
  `id` tinyint(1) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` char(64) NOT NULL DEFAULT '0' COMMENT 'The ID of the order',
  `merchant_loan_id` char(64) NOT NULL DEFAULT '0' COMMENT 'The transaction hash to securize transaction',
  PRIMARY KEY (`id`)
) AUTO_INCREMENT=1 ;
