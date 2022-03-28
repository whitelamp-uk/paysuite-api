
-- Must be a single select query
SELECT
  'PST'
 ,null
 ,CONCAT(`DDRefOrig`,'')
 ,`ClientRef`
 ,`MandateCreated`
 ,`Updated`
 ,`StartDate`
 ,`Status`
 ,`Freq`
 ,`Amount`
 ,`ChancesCsv`
 ,`Name`
 ,`Sortcode`
 ,`Account`
 ,`FailReason`
 ,`MandateId`
 ,1
 ,`MandateCreated`
 ,`StartDate`
FROM `paysuite_mandate`
WHERE `ContractGuid` IS NOT NULL
  AND `ContractGuid`!=''
GROUP BY `MandateId`
ORDER BY `MandateId`
;

