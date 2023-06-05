
-- Must be a single select query
SELECT
  'PST'
 ,CONCAT({{BLOTTO_ORG_ID}},digitsOnly(`MandateId`+{{PST_REFNO_OFFSET}}))
 ,`MandateId`+{{PST_REFNO_OFFSET}}
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
WHERE LENGTH(`ContractGuid`)>0
  AND LENGTH(`DDRefOrig`)>0
{{WHERE}}
GROUP BY `MandateId`
ORDER BY `MandateId`
;

