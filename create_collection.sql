
CREATE TABLE IF NOT EXISTS `paysuite_collection` (
  `CollectionId` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `CollectionCreated` timestamp DEFAULT CURRENT_TIMESTAMP,
  `PaymentGuid` char(64) CHARACTER SET ascii DEFAULT NULL,
  `DDRefOrig` bigint(20) unsigned NOT NULL,
  `ClientRef` char(64) CHARACTER SET ascii DEFAULT NULL,
  `DateDue` date DEFAULT NULL,
  `Amount` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`CollectionId`),
  UNIQUE KEY `PaymentGuid` (`PaymentGuid`),
  UNIQUE KEY `DDRefOrig` (`DDRefOrig`),
  UNIQUE KEY `ClientRef` (`ClientRef`),
  KEY `CollectionCreated` (`CollectionCreated`),
  KEY `DateDue` (`DateDue`),
  KEY `Amount` (`Amount`),
  CONSTRAINT `paysuite_collection_ibfk_1` FOREIGN KEY (`ClientRef`) REFERENCES `paysuite_mandate` (`ClientRef`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
;

