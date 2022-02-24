
-- Must be a single select query
SELECT
  'PST'
 ,null
 ,`DDRefOrig`
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
WHERE 1
GROUP BY `MandateId`
ORDER BY `MandateId`
;

