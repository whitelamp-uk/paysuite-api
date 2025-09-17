


-- mandates

-- get rid of last attempt
DROP TABLE IF EXISTS `paysuite_collection_test`
;
-- copy a data structure, say BWH
CREATE TABLE `paysuite_mandate_test` LIKE `crucible2_bwh_make`.`paysuite_mandate`
;
-- transform
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
  IF(
    `Status` IN ('LIVE','PENDING')
   ,null
   ,`ClientRef`
  )
 ,IF(
    `Status` IN ('LIVE','PENDING')
   ,null
   ,`ClientRef`
  )
 ,IF(
    `Status` IN ('LIVE','PENDING')
   ,null
   ,`DDRefOrig`
  )
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
WHERE `IsCurrent`>0 -- RSM quirk but get all players
ORDER BY `DDRefOrig`
;


-- collections (slow)

-- get rid of last attempt
DROP TABLE IF EXISTS `paysuite_mandate_test`
;
-- copy a data structure, say BWH
CREATE TABLE `paysuite_collection_test` LIKE `crucible2_bwh_make`.`paysuite_collection`
;
-- transform
INSERT INTO `paysuite_collection_test` (
  `PaymentGuid`
 ,`MandateId`
 ,`ClientRef`
 ,`DateDue`
 ,`Amount`
 ,`Status`
)
SELECT
  CONCAT(SUBSTR(`m`.`ClientRef`,1,16),SUBSTR(`m`.`ClientRef`,25,8),'-',`c`.`DateDue`) -- 32-char ClientRefs are too long - column is char(24)
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
-- foreign keys
ALTER TABLE `paysuite_collection_test`
ADD FOREIGN KEY (`MandateId`) REFERENCES `paysuite_mandate_test` (`MandateId`)
;
ALTER TABLE `paysuite_collection_test`
ADD FOREIGN KEY (`ClientRef`) REFERENCES `paysuite_mandate_test` (`ClientRef`)
;


-- the data that normally would be given to insert_mandates() by payment_mandate.php
-- our script is based on payment_mandate.php
-- get rid of last attempt
DROP TABLE IF EXISTS `paysuite_transfer_supporter`
;
-- transform
CREATE TABLE `paysuite_transfer_supporter` AS
SELECT
  `m`.`ClientRef`
 ,'C' AS `Type`
 ,`p`.`chances` AS `Chances`
 ,SUBSTR(`m`.`StartDate`,9,2) AS `PayDay`
 ,`m`.`Name`
 ,`m`.`Sortcode`
 ,`m`.`Account`
 ,`m`.`Amount`
 ,IF(
    `m`.`Freq`='Monthly'
   ,CAST(ROUND(`m`.`Amount`/5,0) AS CHAR CHARACTER SET ascii)
   ,IF(
      `m`.`Freq`='Monthly'
     ,CAST(ROUND(`m`.`Annually`/60,0) AS CHAR CHARACTER SET ascii)
     ,''
    )
  ) AS `ChancesCsv`
 ,`m`.`Freq`
 ,GROUP_CONCAT(`c`.`title` ORDER BY `c`.`id` DESC LIMIT 1) AS `Title`
 ,GROUP_CONCAT(`c`.`name_first` ORDER BY `c`.`id` DESC LIMIT 1) AS `NamesGiven`
 ,GROUP_CONCAT(`c`.`name_last` ORDER BY `c`.`id` DESC LIMIT 1) AS `NamesFamily`
 ,0 AS `EasternOrder`
 ,GROUP_CONCAT(`c`.`email` ORDER BY `c`.`id` DESC LIMIT 1) AS `Email`
 ,GROUP_CONCAT(`c`.`address_1` ORDER BY `c`.`id` DESC LIMIT 1) AS `AddressLine1`
 ,GROUP_CONCAT(`c`.`address_2` ORDER BY `c`.`id` DESC LIMIT 1) AS `AddressLine2`
 ,GROUP_CONCAT(`c`.`address_3` ORDER BY `c`.`id` DESC LIMIT 1) AS `AddressLine3`
 ,GROUP_CONCAT(`c`.`town` ORDER BY `c`.`id` DESC LIMIT 1) AS `Town`
 ,GROUP_CONCAT(`c`.`county` ORDER BY `c`.`id` DESC LIMIT 1) AS `County`
 ,GROUP_CONCAT(`c`.`postcode` ORDER BY `c`.`id` DESC LIMIT 1) AS `Postcode`
 ,GROUP_CONCAT(`c`.`country` ORDER BY `c`.`id` DESC LIMIT 1) AS `Country`
FROM `paysuite_mandate_test` AS `m`
JOIN `blotto_player` AS `p`
  ON `p`.`client_ref`=`m`.`ClientRef`
JOIN `blotto_contact` AS `c`
  ON `c`.`supporter_id`=`p`.`supporter_id`
WHERE `m`.`CustomerGuid` IS NULL -- just those that need creating (live or pending at RSM)
GROUP BY `m`.`MandateId`
;


/*
-- push back anomalous pay days
UPDATE `paysuite_transfer_supporter`
SET
  `PayDay`='08'
WHERE `PayDay`>'01'
  AND `PayDay`<'08'
;
UPDATE `paysuite_transfer_supporter`
SET
  `PayDay`='15'
WHERE `PayDay`>'08'
  AND `PayDay`<'15'
;
UPDATE `paysuite_transfer_supporter`
SET
  `PayDay`='22'
WHERE `PayDay`>'15'
  AND `PayDay`<'22'
;
UPDATE `paysuite_transfer_supporter`
SET
  `PayDay`='01'
WHERE `PayDay`>'22'
;
*/



/*
-- rename the tables
ALTER TABLE `paysuite_mandate_test` RENAME TO `paysuite_mandate`
;
ALTER TABLE `paysuite_collection_test` RENAME TO `paysuite_collection`
;
*/



-- that's all folks

