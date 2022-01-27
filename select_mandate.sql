-- Must be a single select query
SELECT
  '{{PAYSUITE_CODE}}',
  *
FROM `paysuite_mandate`
WHERE 1
GROUP BY `DDRefOrig`
ORDER BY `DDRefOrig`
;
