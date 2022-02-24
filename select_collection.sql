
-- Must be a single select query
SELECT
  `DateDue`
 ,'PST'
 ,null
 ,`DDRefOrig`
 ,`ClientRef`
 ,`Amount`
FROM `paysuite_collection`
WHERE `DateDue`<DATE_SUB(CURDATE(),INTERVAL {{PST_PAY_INTERVAL}})
  AND `Amount`>0
GROUP BY `DDRefOrig`
ORDER BY `DateDue`,`DDRefOrig`
;

