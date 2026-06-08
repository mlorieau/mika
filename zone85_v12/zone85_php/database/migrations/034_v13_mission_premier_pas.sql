-- Migration 034 — V13 : Mission tutorielle "Premier pas dans la Zone"
-- Mission simple pour les nouveaux membres
-- Colonnes confirmées depuis schema.sql : title, slug, mission_type, description, instructions,
-- status, is_collective, validation_mode, requires_vote, xp_participation, xp_success,
-- clan_points_participation, clan_points_success, season_id, created_at
INSERT IGNORE INTO missions (
    title,
    slug,
    mission_type,
    description,
    instructions,
    status,
    is_collective,
    validation_mode,
    requires_answer,
    requires_upload,
    requires_vote,
    requires_code,
    xp_participation,
    xp_success,
    clan_points_participation,
    clan_points_success,
    season_id,
    created_at
) VALUES (
    'Premier pas dans la Zone',
    'premier-pas-dans-la-zone',
    'vote',
    'Découvre ton passeport, ton clan et marque tes premiers XP.',
    'Connecte-toi, consulte ton passeport Zonaute et prends une capture d''écran ou note ton niveau de départ. C''est tout !',
    'active',
    0,
    'auto',
    0,
    0,
    1,
    0,
    20,
    0,
    0,
    0,
    NULL,
    NOW()
);
