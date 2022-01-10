-- Must be a single select query
SELECT
  -- Or whatever column names are used in Paysuiteland
  `DateDue`
 ,'PST'
 ,null
 ,`DDRefOrig`
 ,`ClientRef`
 ,`PaidAmount`
FROM `rsm_collection`
WHERE `DateDue`<DATE_SUB(CURDATE(),INTERVAL {{RSM_PAY_INTERVAL}})
  AND `PayStatus`='PAID'
  AND `Amount`>0
ORDER BY `DateDue`,`DDRefOrig`
;

