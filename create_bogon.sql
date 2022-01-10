


DELIMITER $$
DROP PROCEDURE IF EXISTS `paysuiteBogonCheckClientRef`$$
CREATE PROCEDURE `paysuiteBogonCheckClientRef` (
)
BEGIN
  INSERT INTO `paysuite_bogon`
  SELECT
    null
   ,'Mandate ClientRef:DDRefOrig not 1:1'
   ,CONCAT(
      `m1`.`DDRefOrig`
     ,'[ #'
     ,`m2`.`id`
     ,' '
     ,`m2`.`ClientRef`
     ,' created='
     ,`m2`.`Created`
     ,' start='
     ,`m2`.`StartDate`
     ,' '
     ,`m2`.`Status`
     ,' ] conflicts with [ #'
     ,`m1`.`id`
     ,' '
     ,`m1`.`ClientRef`
     ,' created='
     ,`m1`.`Created`
     ,' start='
     ,`m1`.`StartDate`
     ,' '
     ,`m1`.`Status`
     ,' ]'
    )
   ,null
  FROM `paysuite_mandate` AS `m1`
  JOIN `paysuite_mandate` AS `m2`
    ON (
         `m2`.`DDRefOrig`=`m1`.`DDRefOrig`
     AND `m2`.`ClientRef`!=`m1`.`ClientRef`
    ) 
    OR (
         `m2`.`ClientRef`=`m1`.`ClientRef`
     AND `m2`.`DDRefOrig`!=`m1`.`DDRefOrig`
    ) 
   AND (
       `m2`.`Created`>=`m1`.`Created`
    OR `m2`.`Created` IS NULL
   )
  ;
END$$


DELIMITER $$
DROP PROCEDURE IF EXISTS `paysuiteBogonCheckAmounts`$$
CREATE PROCEDURE `paysuiteBogonCheckAmounts` (
)
BEGIN
  INSERT INTO `paysuite_bogon`
    SELECT
      null
     ,'Mandate Amount not consistent with collection PaidAmount'
     ,CONCAT(
        '[ '
       ,`c`.`ClientRef`
       ,' x'
       ,COUNT(`c`.`id`)
       ,' collections @'
       ,`c`.`PaidAmount`
       ,' ] conflicts with [ '
       ,`m`.`ClientRef`
       ,' mandate @'
       ,`m`.`Amount`
       ,' ]'
      )
     ,null
    FROM `paysuite_collection` AS `c`
    JOIN `paysuite_mandate` AS `m`
      ON `m`.`DDRefOrig`=`c`.`DDRefOrig`
    WHERE `c`.`PaidAmount`>0
      AND `m`.`Amount`!=`c`.`PaidAmount`
    GROUP BY `c`.`DDRefOrig`
  ;
END$$


DELIMITER $$
DROP PROCEDURE IF EXISTS `paysuiteBogonCheckCollections`$$
CREATE PROCEDURE `paysuiteBogonCheckCollections` (
)
BEGIN
  INSERT INTO `paysuite_bogon`
    SELECT
      null
     ,'Collection/mandate DDRefOrig/Clientref inconsistent'
     ,IF (
        `m`.`id` IS NULL
       ,CONCAT(
          '[ '
         ,`c`.`ClientRef`
         ,' x'
         ,COUNT(`c`.`id`)
         ,' collections ] have no corresponding mandate'
        )
       ,CONCAT(
          '[ #'
         ,' '
         ,`c`.`ClientRef`
         ,' x'
         ,COUNT(`c`.`id`)
         ,' collections ] conflicts with [ #'
         ,IF(
            `m`.`id` IS NULL
           ,''
           ,CONCAT(
              `m`.`id`
             ,' '
             ,`m`.`ClientRef`
             ,' '
             ,`m`.`DDRefOrig`
             ,' created='
             ,`m`.`Created`
             ,' start='
             ,`m`.`StartDate`
             ,' '
             ,`m`.`Status`
            )
          )
         ,' ]'
        )
      )
     ,null
    FROM `paysuite_collection` AS `c`
    LEFT JOIN `paysuite_mandate` AS `m`
      ON `m`.`ClientRef`=`c`.`ClientRef`
    WHERE `c`.`PaidAmount`>0
      AND `m`.`id` IS NULL
       OR `m`.`DDRefOrig`!=`c`.`DDRefOrig`
    GROUP BY `m`.`id`
  ;
END$$


DELIMITER $$
DROP PROCEDURE IF EXISTS `paysuiteBogonCheckDateDue`$$
CREATE PROCEDURE `paysuiteBogonCheckDateDue` (
)
BEGIN
  INSERT INTO `paysuite_bogon`
    SELECT
      null
     ,'Collection DateDue not unique per DDRefOrig'
     ,CONCAT_WS(
        ', '
       ,`DDRefOrig`
       ,`ClientRef`
       ,`DateDue`
       ,CONCAT(
          COUNT(`id`)
         ,' payments on the same date'
        )
      )
     ,COUNT(`id`) AS `qty`
    FROM `paysuite_collection`
    WHERE `PaidAmount`>0
    GROUP BY `ClientRef`,`DateDue`
    HAVING `qty`>1
  ;
END$$


DELIMITER $$
DROP PROCEDURE IF EXISTS `paysuiteBogonCheckFreqAmount`$$
CREATE PROCEDURE `paysuiteBogonCheckFreqAmount` (
)
BEGIN
  INSERT INTO `paysuite_bogon`
    SELECT
      null
     ,'Mandate Freq-Amount not unique per DDRefOrig'
     ,CONCAT(
        `m1`.`DDRefOrig`
       ,'[ #'
       ,`m2`.`id`
       ,' created='
       ,`m2`.`Created`
       ,' start='
       ,`m2`.`StartDate`
       ,' '
       ,`m2`.`Status`
       ,' ] conflicts with [ #'
       ,`m1`.`id`
       ,' created='
       ,`m1`.`Created`
       ,' start='
       ,`m1`.`StartDate`
       ,' '
       ,`m1`.`Status`
       ,' ]'
      )
     ,null
    FROM `paysuite_mandate` AS `m1`
    JOIN `paysuite_mandate` AS `m2`
      ON `m2`.`DDRefOrig`=`m1`.`DDRefOrig`
     AND (
         `m2`.`Amount`!=`m1`.`Amount`
      OR `m2`.`Freq`!=`m1`.`Freq`
     )
     AND `m2`.`id`!=`m1`.`id`
     AND (
         `m2`.`Created`>=`m1`.`Created`
      OR `m2`.`Created` IS NULL
     )
  ;
END$$


DELIMITER $$
DROP PROCEDURE IF EXISTS `paysuiteBogonCheckPaidAmount`$$
CREATE PROCEDURE `paysuiteBogonCheckPaidAmount` (
)
BEGIN
  INSERT INTO `paysuite_bogon`
    SELECT
      null
     ,'Collection PaidAmount not unique per DDRefOrig'
     ,CONCAT_WS(
        ', '
       ,`DDRefOrig`
       ,`ClientRef`
       ,CONCAT(
          COUNT(DISTINCT `PaidAmount`)
         ,' different collection amounts: '
         ,GROUP_CONCAT(DISTINCT `PaidAmount`)
        )
      )
     ,COUNT(DISTINCT `PaidAmount`) AS `qty`
    FROM `paysuite_collection`
    WHERE `PaidAmount`>0
    GROUP BY `ClientRef`
    HAVING `qty`>1
  ;
END$$


DROP TABLE IF EXISTS `paysuite_bogon`;

CREATE TABLE `paysuite_bogon` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(64) CHARACTER SET ascii DEFAULT NULL,
  `details` varchar(255) CHARACTER SET ascii DEFAULT NULL,
  `tmp` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
;

CALL `paysuiteBogonCheckDateDue`();
CALL `paysuiteBogonCheckPaidAmount`();
CALL `paysuiteBogonCheckClientRef`();
CALL `paysuiteBogonCheckCollections`();
CALL `paysuiteBogonCheckFreqAmount`();
CALL `paysuiteBogonCheckAmounts`();

ALTER TABLE `paysuite_bogon`
DROP COLUMN `tmp`
;

DROP PROCEDURE `paysuiteBogonCheckDateDue`;
DROP PROCEDURE `paysuiteBogonCheckPaidAmount`;
DROP PROCEDURE `paysuiteBogonCheckClientRef`;
DROP PROCEDURE `paysuiteBogonCheckCollections`;
DROP PROCEDURE `paysuiteBogonCheckFreqAmount`;
DROP PROCEDURE `paysuiteBogonCheckAmounts`;

