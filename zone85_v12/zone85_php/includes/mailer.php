<?php
// ============================================================
// ZONE 85 — Mailer V10
// Abstraction email : Brevo API (si configuré) ou mail() PHP.
// Utilise la table email_queue pour les envois différés.
// ============================================================

/**
 * Ajoute un email en file d'attente (email_queue).
 * Retourne true si ajouté, false si DB indisponible.
 */
function queue_email(
    string $to_email,
    string $template_slug,
    array  $variables = [],
    ?int   $user_id   = null,
    ?string $to_name  = null,
    ?string $subject  = null,
    ?\DateTime $scheduled_at = null
): bool {
    $pdo = db();
    if (!$pdo) return false;

    // Récupère le sujet depuis email_templates si non fourni
    if ($subject === null) {
        try {
            $s = $pdo->prepare("SELECT subject FROM email_templates WHERE slug = :s LIMIT 1");
            $s->execute([':s' => $template_slug]);
            $tpl = $s->fetch();
            $subject = $tpl ? $tpl['subject'] : 'Notification Zone85';
        } catch (PDOException $e) {
            $subject = 'Notification Zone85';
        }
    }

    try {
        $pdo->prepare("
            INSERT INTO email_queue
                (user_id, to_email, to_name, template_slug, subject, variables, scheduled_at)
            VALUES
                (:uid, :email, :name, :tpl, :subj, :vars, :sched)
        ")->execute([
            ':uid'   => $user_id,
            ':email' => $to_email,
            ':name'  => $to_name,
            ':tpl'   => $template_slug,
            ':subj'  => $subject,
            ':vars'  => json_encode($variables, JSON_UNESCAPED_UNICODE),
            ':sched' => $scheduled_at ? $scheduled_at->format('Y-m-d H:i:s') : null,
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('[ZONE85 mailer] queue_email : ' . $e->getMessage());
        return false;
    }
}

/**
 * Envoie immédiatement un email.
 * Utilise Brevo API si configuré, sinon mail() PHP.
 *
 * $variables : données pour le template (ex: ['pseudo' => 'Mickaël', 'badge' => 'Pionnier'])
 *
 * Retourne ['ok' => bool, 'error' => string|null]
 */
function send_email(
    string $to_email,
    string $subject,
    string $template_slug,
    array  $variables = [],
    ?string $to_name = null
): array {
    if (get_setting('brevo_enabled', false) && !empty(get_setting('brevo_api_key', ''))) {
        return _send_via_brevo($to_email, $to_name, $subject, $template_slug, $variables);
    }
    return _send_via_php_mail($to_email, $to_name, $subject, $template_slug, $variables);
}

/**
 * Envoi via Brevo API transactionnelle.
 * Nécessite que le template_slug corresponde à un brevo_id dans email_templates.
 */
function _send_via_brevo(string $to_email, ?string $to_name, string $subject, string $template_slug, array $variables): array {
    $pdo = db();
    $brevo_id = null;

    if ($pdo) {
        try {
            $s = $pdo->prepare("SELECT brevo_id FROM email_templates WHERE slug = :s LIMIT 1");
            $s->execute([':s' => $template_slug]);
            $row = $s->fetch();
            $brevo_id = $row ? (int)$row['brevo_id'] : null;
        } catch (PDOException $e) {
            error_log('[ZONE85 mailer] brevo template lookup : ' . $e->getMessage());
        }
    }

    $payload = [
        'to'     => [['email' => $to_email, 'name' => $to_name ?: $to_email]],
        'sender' => ['email' => get_setting('brevo_from_email', 'noreply@zone85.fr'), 'name' => get_setting('brevo_from_name', 'ZONE85')],
        'params' => $variables,
    ];

    if ($brevo_id) {
        $payload['templateId'] = $brevo_id;
    } else {
        // Fallback : envoi HTML basique si template Brevo non configuré
        $payload['subject']     = $subject;
        $payload['htmlContent'] = _build_fallback_html($subject, $variables, $template_slug);
    }

    $ch = curl_init(get_setting('brevo_api_url', 'https://api.brevo.com/v3/smtp/email'));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . get_setting('brevo_api_key', ''),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log('[ZONE85 mailer] Brevo curl error : ' . $err);
        return ['ok' => false, 'error' => 'Curl : ' . $err];
    }
    if ($code < 200 || $code >= 300) {
        error_log('[ZONE85 mailer] Brevo HTTP ' . $code . ' : ' . $resp);
        return ['ok' => false, 'error' => 'HTTP ' . $code];
    }
    return ['ok' => true, 'error' => null];
}

/**
 * Envoi via mail() PHP natif (fallback sans Brevo).
 */
function _send_via_php_mail(string $to_email, ?string $to_name, string $subject, string $template_slug, array $variables): array {
    $from_email = get_setting('brevo_from_email', 'noreply@zone85.fr');
    $from_name  = get_setting('brevo_from_name', 'ZONE85');

    $html = _build_fallback_html($subject, $variables, $template_slug);

    $boundary = '----=_Part_' . md5(uniqid());
    $headers  = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'X-Mailer: Zone85-Mailer/10',
    ]);

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=utf-8\r\n\r\n"
          . $html . "\r\n"
          . "--{$boundary}--";

    $to = $to_name ? '"' . addslashes($to_name) . '" <' . $to_email . '>' : $to_email;

    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    return ['ok' => $ok, 'error' => $ok ? null : 'mail() a retourné false'];
}

/**
 * Génère un HTML email minimaliste pour le fallback sans templates Brevo.
 */
function _build_fallback_html(string $subject, array $vars, string $template_slug): string {
    $site_url = defined('SITE_URL') ? SITE_URL : 'https://www.zone85.fr';
    $pseudo   = htmlspecialchars($vars['pseudo'] ?? '', ENT_QUOTES);

    // Corps spécifique par template
    $body_content = match($template_slug) {
        'welcome' =>
            '<h2 style="margin:0 0 16px;color:#0c1e2e">Bienvenue dans la Zone ! 🎉</h2>' .
            '<p>Salut <strong>' . $pseudo . '</strong>,</p>' .
            '<p>Ton compte Zone85 est activé. Rejoins ton clan et commence à gagner des XP !</p>' .
            '<a href="' . $site_url . '/missions.php" style="display:inline-block;background:#ea5649;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:800;margin-top:16px">Voir les missions →</a>',
        'email_verification' =>
            '<h2 style="margin:0 0 16px;color:#0c1e2e">Confirme ton adresse email</h2>' .
            '<p>Salut <strong>' . $pseudo . '</strong>,</p>' .
            '<p>Clique sur le bouton ci-dessous pour confirmer ton adresse email et activer pleinement ton compte Zone85.</p>' .
            '<a href="' . htmlspecialchars($vars['verify_url'] ?? '#', ENT_QUOTES) . '" style="display:inline-block;background:#ea5649;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:800;margin-top:16px">Confirmer mon email →</a>' .
            '<p style="margin-top:16px;font-size:.8rem;color:#9ca3af">Ce lien est valable 48h. S\'il ne fonctionne pas, copie-colle l\'URL dans ton navigateur.</p>',
        'password_reset' =>
            '<h2 style="margin:0 0 16px;color:#0c1e2e">Réinitialisation de ton mot de passe</h2>' .
            '<p>Salut <strong>' . $pseudo . '</strong>,</p>' .
            '<p>Tu as demandé à réinitialiser ton mot de passe. Clique sur le bouton ci-dessous :</p>' .
            '<a href="' . htmlspecialchars($vars['reset_url'] ?? '#', ENT_QUOTES) . '" style="display:inline-block;background:#ea5649;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:800;margin-top:16px">Réinitialiser mon mot de passe →</a>' .
            '<p style="margin-top:16px;font-size:.8rem;color:#9ca3af">Ce lien est valable <strong>1 heure</strong>. Si tu n\'es pas à l\'origine de cette demande, ignore cet email.</p>',
        'badge_unlock' =>
            '<h2 style="margin:0 0 16px;color:#0c1e2e">🏅 Nouveau badge débloqué !</h2>' .
            '<p>Félicitations <strong>' . $pseudo . '</strong> !</p>' .
            '<p>Tu as obtenu le badge : <strong>' . htmlspecialchars($vars['badge_name'] ?? '', ENT_QUOTES) . '</strong>.</p>',
        'mission_new' =>
            '<h2 style="margin:0 0 16px;color:#0c1e2e">🎯 Nouvelle mission disponible !</h2>' .
            '<p>Salut <strong>' . $pseudo . '</strong>,</p>' .
            '<p>Une nouvelle mission vient d\'être publiée : <strong>' . htmlspecialchars($vars['mission_title'] ?? '', ENT_QUOTES) . '</strong>.</p>' .
            '<a href="' . $site_url . '/missions.php" style="display:inline-block;background:#ea5649;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:800;margin-top:16px">Voir la mission →</a>',
        'mission_validated' =>
            '<h2 style="margin:0 0 16px;color:#0c1e2e">✅ Participation validée !</h2>' .
            '<p>Salut <strong>' . $pseudo . '</strong>,</p>' .
            '<p>Ta participation à <strong>' . htmlspecialchars($vars['mission_title'] ?? '', ENT_QUOTES) . '</strong> a été validée.' .
            (!empty($vars['xp_awarded']) ? ' Tu gagnes <strong>+' . (int)$vars['xp_awarded'] . ' XP</strong> !' : '') . '</p>' .
            '<a href="' . $site_url . '/profil.php" style="display:inline-block;background:#ea5649;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:800;margin-top:16px">Voir mon profil →</a>',
        'delete_requested' =>
            '<h2 style="margin:0 0 16px;color:#0c1e2e">Demande de suppression reçue</h2>' .
            '<p>Nous avons bien reçu ta demande de suppression de compte.</p>' .
            '<p>Ton compte sera anonymisé sous 30 jours. Tu peux annuler cette demande en nous contactant.</p>',
        default =>
            '<h2 style="margin:0 0 16px;color:#0c1e2e">' . htmlspecialchars($subject, ENT_QUOTES) . '</h2>' .
            '<p>Notification de Zone85.</p>',
    };

    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f8f4ef;font-family:Arial,sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">'
        . '<tr><td style="background:#0c1e2e;padding:20px 28px"><span style="font-size:.72rem;font-weight:900;letter-spacing:.2em;color:#ea5649;text-transform:uppercase">ZONE85</span></td></tr>'
        . '<tr><td style="padding:28px 28px 20px;color:#0f1e2d;font-size:.92rem;line-height:1.6">'
        . $body_content
        . '</td></tr>'
        . '<tr><td style="background:#f8f4ef;padding:16px 28px;font-size:.74rem;color:#6b7f96;text-align:center">'
        . '© 2025 Zone85 — <a href="' . $site_url . '/confidentialite.php" style="color:#6b7f96">Confidentialité</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}

/**
 * Traite la file d'attente (à appeler depuis un cron ou un script).
 * Envoie jusqu'à $limit emails en attente.
 */
function process_email_queue(int $limit = 20): array {
    $pdo = db();
    if (!$pdo) return ['sent' => 0, 'failed' => 0];

    $sent = 0; $failed = 0;

    try {
        $rows = $pdo->query("
            SELECT * FROM email_queue
            WHERE status = 'pending'
              AND (scheduled_at IS NULL OR scheduled_at <= NOW())
              AND attempts < 3
            ORDER BY created_at ASC
            LIMIT {$limit}
        ")->fetchAll();

        foreach ($rows as $row) {
            // Marquer en cours
            $pdo->prepare("UPDATE email_queue SET status='sending', attempts=attempts+1 WHERE id=:id")
                ->execute([':id' => $row['id']]);

            $vars = json_decode($row['variables'] ?? '{}', true) ?: [];
            $res  = send_email(
                $row['to_email'], $row['subject'],
                $row['template_slug'], $vars, $row['to_name']
            );

            if ($res['ok']) {
                $pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=:id")
                    ->execute([':id' => $row['id']]);
                $sent++;
            } else {
                $err = $res['error'] ?? 'Erreur inconnue';
                $pdo->prepare("UPDATE email_queue SET status='failed', last_error=:e WHERE id=:id")
                    ->execute([':e' => $err, ':id' => $row['id']]);
                $failed++;
            }
        }
    } catch (PDOException $e) {
        error_log('[ZONE85 mailer] process_queue : ' . $e->getMessage());
    }

    return ['sent' => $sent, 'failed' => $failed];
}
