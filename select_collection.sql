
-- Must be a single select query
SELECT
  `DateDue`
 ,'PST'
 ,null
 ,`MandateId`+{{PST_REFNO_OFFSET}}
 ,`ClientRef`
 ,`Amount`
FROM `paysuite_collection`
WHERE `DateDue`<DATE_SUB(CURDATE(),INTERVAL {{PST_PAY_INTERVAL}})
  AND `Amount`>0
GROUP BY `MandateId`
ORDER BY `DateDue`,`MandateId`
;

