-- Must be a single select query
SELECT
  *
FROM `paysuite_mandate`
WHERE 1
GROUP BY `DDRefOrig`
ORDER BY `DDRefOrig`
;
