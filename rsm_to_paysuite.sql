
-- mandates
DROP TABLE IF EXISTS `paysuite_mandate_test`
;
CREATE TABLE `paysuite_mandate_test` LIKE `crucible2_bwh_make`.`paysuite_mandate`
;
INSERT INTO `paysuite_mandate_test` (
  `CustomerGuid`
 ,`ContractGuid`
 ,`DDRefOrig`
 ,`ClientRef`
 ,`Name`
 ,`Sortcode`
 ,`Account`
 ,`StartDate`
 ,`Freq`
 ,`Amount`
 ,`ChancesCsv`
 ,`Status`
 ,`FailReason`
)
SELECT
  `ClientRef`
 ,`ClientRef`
 ,`DDRefOrig`
 ,`ClientRef`
 ,`Name`
 ,`Sortcode`
 ,`Account`
 ,`StartDate`
 ,`Freq`
 ,`Amount`
 ,`ChancesCsv`
 ,`Status`
 ,`FailReason`
FROM `rsm_mandate`
WHERE `IsCurrent`>0
ORDER BY `DDRefOrig`
;
-- only create active DDIs remotely
UPDATE `paysuite_mandate_test` AS `mnew`
JOIN `rsm_mandate` AS `mold`
  ON `IsCurrent`>0
 AND `mold`.`Status` IN ('LIVE','PENDING')
 AND `mold`.`ClientRef`=`mnew`.`ClientRef`
SET
  `mnew`.`CustomerGuid`=null
 ,`mnew`.`ContractGuid`=null
 ,`mnew`.`DDRefOrig`=null
;


-- collections (very slow)
DROP TABLE IF EXISTS `paysuite_collection_test`
;
CREATE TABLE `paysuite_collection_test` LIKE `crucible2_bwh_make`.`paysuite_collection`
;
INSERT INTO `paysuite_collection_test` (
  `PaymentGuid`
 ,`MandateId`
 ,`ClientRef`
 ,`DateDue`
 ,`Amount`
 ,`Status`
)
SELECT
  CONCAT(SUBSTR(`m`.`ClientRef`,1,16),SUBSTR(`m`.`ClientRef`,25,8),'-',`c`.`DateDue`) -- 32-char ClientRefs are too long - column is char(36)
 ,`m`.`MandateId`
 ,`m`.`ClientRef`
 ,`c`.`DateDue`
 ,`c`.`PaidAmount`
 ,'PAID'
FROM `rsm_collection` AS `c`
JOIN `paysuite_mandate_test` AS `m`
  ON `m`.`ClientRef`=`c`.`ClientRef`
WHERE `c`.`PayStatus`='PAID' -- no need to copy across UNPAID collections
ORDER BY `DateDue`,`ClientRef`
;


-- foreign keys (a bit slow)
ALTER TABLE `paysuite_collection_test`
ADD FOREIGN KEY (`MandateId`) REFERENCES `paysuite_mandate_test` (`MandateId`)
;
ALTER TABLE `paysuite_collection_test`
ADD FOREIGN KEY (`ClientRef`) REFERENCES `paysuite_mandate_test` (`ClientRef`)
;


-- rename the tables
ALTER TABLE `paysuite_mandate_test` RENAME TO `paysuite_mandate`
;
ALTER TABLE `paysuite_collection_test` RENAME TO `paysuite_collection`
;


-- that's all folks

