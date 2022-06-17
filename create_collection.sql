
CREATE TABLE IF NOT EXISTS `paysuite_collection` (
  `CollectionId` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `CollectionCreated` timestamp DEFAULT CURRENT_TIMESTAMP,
  `PaymentGuid` char(36) CHARACTER SET ascii DEFAULT NULL,
  `MandateId` int(11) unsigned NOT NULL,
  `ClientRef` char(64) CHARACTER SET ascii DEFAULT NULL,
  `DateDue` date DEFAULT NULL,
  `Amount` decimal(10,2) DEFAULT NULL,
  `Status` char(8),
  `OriginalStatus` char(8),
  `StatusChanged` datetime DEFAULT NULL,
  `Updated` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`CollectionId`),
  UNIQUE KEY `PaymentGuid` (`PaymentGuid`),
  KEY `MandateId` (`MandateId`),
  KEY `ClientRef` (`ClientRef`),
  KEY `CollectionCreated` (`CollectionCreated`),
  KEY `DateDue` (`DateDue`),
  KEY `Amount` (`Amount`),
  CONSTRAINT `paysuite_collection_ibfk_1` FOREIGN KEY (`MandateId`) REFERENCES `paysuite_mandate` (`MandateId`),
  CONSTRAINT `paysuite_collection_ibfk_2` FOREIGN KEY (`ClientRef`) REFERENCES `paysuite_mandate` (`ClientRef`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
;

