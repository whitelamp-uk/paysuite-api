
CREATE TABLE IF NOT EXISTS `paysuite_mandate` (
  `MandateId` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `MandateCreated` timestamp DEFAULT CURRENT_TIMESTAMP,
  `CustomerGuid` char(36) CHARACTER SET ascii DEFAULT NULL,
  `ContractGuid` char(36) CHARACTER SET ascii DEFAULT NULL,
  `DDRefOrig` char(64) CHARACTER SET ascii DEFAULT NULL,
  `ClientRef` char(255) CHARACTER SET ascii DEFAULT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Sortcode` varchar(255) DEFAULT NULL,
  `Account` varchar(255) DEFAULT NULL,
  `StartDate` varchar(255) DEFAULT NULL,
  `Freq` varchar(255) DEFAULT NULL,
  `Amount` decimal(10,2) DEFAULT NULL,
  `ChancesCsv` varchar(255) CHARACTER SET ascii NOT NULL,
  `Status` char(64) CHARACTER SET ascii NOT NULL DEFAULT '',
  `FailReason` varchar(255) NOT NULL,
  `Updated` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`MandateId`),
  UNIQUE KEY `CustomerGuid` (`CustomerGuid`),
  UNIQUE KEY `ContractGuid` (`ContractGuid`),
  UNIQUE KEY `DDRefOrig` (`DDRefOrig`),
  UNIQUE KEY `ClientRef` (`ClientRef`),
  KEY `MandateCreated` (`MandateCreated`),
  KEY `Freq` (`Freq`),
  KEY `Amount` (`Amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
;

