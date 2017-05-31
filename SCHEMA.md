
```
CREATE TABLE `barcodes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `store` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


CREATE TABLE `inventory_sync_tokens` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `store` varchar(100) NOT NULL DEFAULT '',
  `token` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


CREATE TABLE `reports` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `store` varchar(100) NOT NULL DEFAULT '',
  `report` longblob NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```
