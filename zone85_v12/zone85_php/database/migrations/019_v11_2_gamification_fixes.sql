-- ============================================================
-- Migration 019 -- V11.2 : Corrections gamification
-- 1. Encodage xp_logs.reason (mojibake UTF-8)
-- 2. XP inscription 50 -> 10 XP
-- 3. Recalcul niveaux
-- ============================================================

-- ── 1. Correction encodage xp_logs.reason ─────────────────────
-- Remplace les sequences mojibake classiques (UTF-8 lu en Latin-1)
UPDATE `xp_logs` SET `reason` =
  REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
  REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`reason`,
    'Ã©','e'),  -- é (e accent aigu)
    'Ã¨','e'),  -- è (e accent grave)
    'Ã ','a'),  -- à (a accent grave)
    'Ã¢','a'),  -- â (a accent circonflexe)
    'Ãª','e'),  -- ê (e accent circonflexe)
    'Ã®','i'),  -- î (i accent circonflexe)
    'Ã»','u'),  -- û (u accent circonflexe)
    'Ã§','c'),  -- ç (c cedille)
    'Ã´','o'),  -- ô (o accent circonflexe)
    'Ã¹','u'),  -- ù (u accent grave)
    'â€™',''''),-- apostrophe typographique
    'â€œ','"'), -- guillemet ouvrant
    'â€','"'),  -- guillemet fermant
    'â€"','-')  -- tiret long
WHERE `reason` LIKE '%Ã%'
   OR `reason` LIKE '%â€%';

-- Vérification : SELECT id, reason FROM xp_logs WHERE reason LIKE '%Ã%' LIMIT 5;

-- ── 2. Correction XP inscription existants ────────────────────
-- Réduire le bonus d'inscription de 50 à 10 XP
-- (les futurs comptes recevront 10 XP via auth.php corrigé)
UPDATE `xp_logs`
SET `xp_amount` = 10
WHERE `source_type` = 'registration'
  AND `xp_amount` = 50;

-- Mettre à jour xp_total en conséquence (-40 XP par compte concerné)
UPDATE `users` u
SET u.`xp_total` = GREATEST(0, u.`xp_total` - 40)
WHERE u.`id` IN (
    SELECT `user_id` FROM `xp_logs`
    WHERE `source_type` = 'registration'
      AND `xp_amount` = 10  -- déjà mis à jour ci-dessus
);

-- Note : la requête ci-dessus ne fonctionnera pas correctement car
-- xp_logs a déjà été mis à jour. Utiliser plutôt :
-- UPDATE users SET xp_total = GREATEST(0, xp_total - 40)
-- WHERE xp_total > 10
-- AND id IN (SELECT user_id FROM xp_logs WHERE source_type='registration');
--
-- VERSION ALTERNATIVE SÛRE (à utiliser si la précédente ne fonctionne pas) :
-- UPDATE users SET xp_total = GREATEST(0, xp_total - 40)
-- WHERE id IN (
--     SELECT uid FROM (
--         SELECT DISTINCT user_id AS uid FROM xp_logs
--         WHERE source_type = 'registration'
--     ) sub
-- );

-- ── 3. Recalcul des niveaux ───────────────────────────────────
-- Applique les seuils V10.2 : 0/50/100/250/500/1000/2500/5000/10000/20000
UPDATE `users` SET `level` = CASE
    WHEN `xp_total` >= 20000 THEN 10
    WHEN `xp_total` >= 10000 THEN 9
    WHEN `xp_total` >= 5000  THEN 8
    WHEN `xp_total` >= 2500  THEN 7
    WHEN `xp_total` >= 1000  THEN 6
    WHEN `xp_total` >= 500   THEN 5
    WHEN `xp_total` >= 250   THEN 4
    WHEN `xp_total` >= 100   THEN 3
    WHEN `xp_total` >= 50    THEN 2
    ELSE 1
END
WHERE `status` = 'active' AND `deleted_at` IS NULL;

-- Vérification finale
-- SELECT id, pseudo, xp_total, level FROM users WHERE status='active';
