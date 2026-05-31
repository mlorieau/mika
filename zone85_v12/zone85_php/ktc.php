<?php
// ============================================================
// V11 : KTC devient une rubrique editoriale mensuelle premium.
// 1 objet mystere par mois · 1 rencontre locale · 4 phases
// DB : ktc_episodes, ktc_episode_photos, ktc_propositions, ktc_votes
// Admin : admin/ktc-episodes.php (V12)
// ============================================================
$page_title       = 'Keto Kole Tche — Zone85';
$page_description = 'Chaque mois, un objet mysterieux vendeen et la rencontre avec un passionnne local. Quel est cet objet ? Keto Kole Tche !';
$page_canonical   = 'https://www.zone85.fr/ktc.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$current_page     = 'ktc';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

// ── Session & utilisateur ─────────────────────────────────────
$user_session = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['user'] ?? null) : null;
$user_id      = $user_session ? (int)$user_session['id'] : 0;
$is_logged    = $user_id > 0;
$csrf         = csrf_token();

// ── Chargement DB ─────────────────────────────────────────────
$question     = null;
$user_answers = [];
$user_stats   = ['total' => 0, 'correct' => 0, 'xp' => 0];
$cat_counts   = [];

try {
    $pdo = db();
    if ($pdo) {

        // 1. IDs déjà répondus (si connecté)
        $answered_ids = [];
        if ($is_logged) {
            $st = $pdo->prepare('SELECT question_id FROM ktc_answers WHERE user_id = :uid');
            $st->execute([':uid' => $user_id]);
            $answered_ids = $st->fetchAll(PDO::FETCH_COLUMN);
        }

        // 2. Question aléatoire (exclure déjà répondues si connecté)
        if ($is_logged && !empty($answered_ids)) {
            $placeholders = implode(',', array_fill(0, count($answered_ids), '?'));
            $st2 = $pdo->prepare(
                'SELECT * FROM ktc_questions
                 WHERE is_active = 1
                   AND id NOT IN (' . $placeholders . ')
                 ORDER BY RAND()
                 LIMIT 1'
            );
            $st2->execute(array_values($answered_ids));
            $question = $st2->fetch() ?: null;
            // Si toutes répondues, prendre n'importe laquelle
            if (!$question) {
                $st3 = $pdo->prepare('SELECT * FROM ktc_questions WHERE is_active = 1 ORDER BY RAND() LIMIT 1');
                $st3->execute();
                $question = $st3->fetch() ?: null;
            }
        } else {
            $st2 = $pdo->prepare('SELECT * FROM ktc_questions WHERE is_active = 1 ORDER BY RAND() LIMIT 1');
            $st2->execute();
            $question = $st2->fetch() ?: null;
        }

        // 3. Stats si connecté
        if ($is_logged) {
            $st4 = $pdo->prepare(
                'SELECT COUNT(*) AS total, SUM(is_correct) AS correct, COALESCE(SUM(xp_earned), 0) AS xp
                 FROM ktc_answers
                 WHERE user_id = :uid'
            );
            $st4->execute([':uid' => $user_id]);
            $row = $st4->fetch();
            if ($row) {
                $user_stats = [
                    'total'   => (int)$row['total'],
                    'correct' => (int)$row['correct'],
                    'xp'      => (int)$row['xp'],
                ];
            }
        }

        // 4. Counts par catégorie
        $st5 = $pdo->prepare('SELECT category, COUNT(*) AS cnt FROM ktc_questions WHERE is_active = 1 GROUP BY category');
        $st5->execute();
        while ($row = $st5->fetch()) {
            $cat_counts[$row['category']] = (int)$row['cnt'];
        }
    }
} catch (Exception $e) {
    // Dégradé silencieux
}

// Active season pour footer
try {
    $active_season = (db_enabled() && function_exists('fetch_active_season')) ? fetch_active_season() : null;
} catch (Exception $e) {
    $active_season = null;
}

// ── Catégories définies ───────────────────────────────────────
$categories = [
    ['slug' => 'expressions', 'label' => 'Le Parler Vendéen',         'emoji' => '🗣️',  'desc' => 'Expressions, patois et tournures vendéennes'],
    ['slug' => 'quiz',        'label' => 'La Vendée et ses Secrets',   'emoji' => '🧠',  'desc' => 'Culture générale vendéenne'],
    ['slug' => 'devinettes',  'label' => 'Joue avec les mots du bocage', 'emoji' => '🎭', 'desc' => 'Devinettes et jeux de mots du terroir'],
    ['slug' => 'histoire',    'label' => 'La Vendée à travers les âges', 'emoji' => '📜', 'desc' => 'Histoire, guerres de Vendée, patrimoine'],
    ['slug' => 'nature',      'label' => 'Bocage, marais, littoral',   'emoji' => '🌿',  'desc' => 'Faune, flore et paysages vendéens'],
    ['slug' => 'gastronomie', 'label' => 'À table !',                  'emoji' => '🥐',  'desc' => 'Saveurs et recettes du terroir vendéen'],
];

// ── Décodage des options ──────────────────────────────────────
$options = [];
if ($question) {
    if (!empty($question['options'])) {
        $decoded = json_decode($question['options'], true);
        $options = is_array($decoded) ? $decoded : [];
    }
    if (empty($options)) {
        foreach (['option_a', 'option_b', 'option_c', 'option_d'] as $k => $col) {
            if (!empty($question[$col])) {
                $options[chr(65 + $k)] = $question[$col];
            }
        }
    }
}

// Déjà répondu à cette question ?
$already_answered = ($question && $is_logged && in_array($question['id'], $answered_ids ?? []));

// Étoiles de difficulté
function _difficulty_stars(int $d): string {
    $d = max(1, min(5, $d));
    return str_repeat('★', $d) . str_repeat('☆', 5 - $d);
}

// ── Styles ────────────────────────────────────────────────────
$page_styles = '<style>
/* ============================================================
   KTC PAGE V11 — Mobile-first
============================================================ */

/* HERO — plus sombre, plus mystérieux */
.ktc-hero {
  background:
    radial-gradient(circle at 80% 15%, rgba(50,10,10,.55), transparent 35%),
    radial-gradient(circle at 12% 88%, rgba(18,49,78,.6), transparent 38%),
    linear-gradient(160deg, #0a0a0a 0%, #150800 45%, #0d0508 100%);
  padding: 120px 0 72px;
  position: relative;
  overflow: hidden;
}
.ktc-hero::before {
  content: "🥐";
  position: absolute;
  right: 5%;
  top: 50%;
  transform: translateY(-50%);
  font-size: 13rem;
  opacity: .05;
  pointer-events: none;
  line-height: 1;
  user-select: none;
}
.ktc-hero-inner { position: relative; z-index: 1; }

/* Badge mystère EN-TÊTE */
.ktc-hero-season-label {
  display: inline-block;
  background: rgba(220,30,30,.22);
  border: 1px solid rgba(220,30,30,.4);
  color: #e84040;
  font-size: .68rem;
  font-weight: 900;
  letter-spacing: .16em;
  text-transform: uppercase;
  padding: 5px 14px;
  border-radius: 4px;
  margin-bottom: 16px;
}

.ktc-hero h1 {
  font-size: clamp(1.8rem, 4.5vw, 3rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -1px;
  line-height: 1.1;
  margin-bottom: 14px;
}
.ktc-hero-sub {
  font-size: .97rem;
  color: rgba(255,255,255,.48);
  max-width: 500px;
  line-height: 1.7;
}

/* Ancien badge Quiz Vendée — conservé mais repositionné */
.ktc-hero-badge {
  display: inline-block;
  background: rgba(201,150,42,.18);
  border: 1px solid rgba(201,150,42,.35);
  color: #d4a830;
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  padding: 5px 14px;
  border-radius: 999px;
  margin-top: 20px;
  display: none; /* remplacé par le label rouge */
}

/* SECTION */
.ktc-section { background: #f5efe6; padding: 64px 0 96px; }
.section-title {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 28px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(18,49,78,.1);
}
/* Titre MYSTÈRE renommé */
.section-title-mystere {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: #8a1a10;
  margin-bottom: 28px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(180,30,20,.15);
}

/* QUIZ CARD */
.ktc-quiz-card {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 6px 32px rgba(139,90,43,.13);
  overflow: hidden;
  margin-bottom: 48px;
  border: 1px solid rgba(139,90,43,.12);
}
.quiz-card-header {
  background: linear-gradient(135deg, #1a0a00 0%, #3a1800 100%);
  padding: 28px 32px 22px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.quiz-category-badge {
  display: inline-block;
  background: rgba(201,150,42,.25);
  color: #d4a830;
  font-size: .65rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .1em;
  padding: 4px 12px;
  border-radius: 999px;
  border: 1px solid rgba(201,150,42,.35);
}
.quiz-difficulty { font-size: .8rem; color: rgba(255,255,255,.45); letter-spacing: .04em; }
.quiz-difficulty span { color: #d4a830; }
.quiz-question-text {
  font-size: 1.15rem;
  font-weight: 800;
  color: #fff;
  line-height: 1.45;
  margin-top: 14px;
}
.quiz-card-body { padding: 28px 32px 32px; }
.quiz-options {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  margin-bottom: 24px;
}
.quiz-option-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f8f3ec;
  border: 2px solid rgba(139,90,43,.12);
  border-radius: 12px;
  padding: 16px 18px;
  cursor: pointer;
  font-family: "Inter", sans-serif;
  font-size: .9rem;
  font-weight: 600;
  color: var(--navy-dark);
  text-align: left;
  transition: all .18s ease;
  width: 100%;
}
.quiz-option-btn:hover:not(:disabled) {
  border-color: rgba(201,150,42,.5);
  background: rgba(201,150,42,.08);
  transform: translateY(-1px);
}
.quiz-option-btn:disabled { cursor: default; }
.quiz-option-btn.selected  { border-color: #c9962a; background: rgba(201,150,42,.12); }
.quiz-option-btn.correct   { border-color: #2a8c40; background: rgba(42,140,64,.1);  color: #1a5c28; }
.quiz-option-btn.incorrect { border-color: #c94030; background: rgba(201,64,48,.08); color: #8a1a10; }
.quiz-option-btn.revealed-correct { border-color: #2a8c40; background: rgba(42,140,64,.06); }
.quiz-opt-letter {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: rgba(139,90,43,.12);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .8rem;
  font-weight: 900;
  color: #5a3010;
  flex-shrink: 0;
  transition: background .18s;
}
.quiz-option-btn.correct .quiz-opt-letter           { background: #2a8c40; color: #fff; }
.quiz-option-btn.incorrect .quiz-opt-letter         { background: #c94030; color: #fff; }
.quiz-option-btn.revealed-correct .quiz-opt-letter  { background: #2a8c40; color: #fff; }

/* RESULT PANEL */
.quiz-result { display: none; border-radius: 12px; padding: 18px 20px; margin-top: 4px; margin-bottom: 8px; }
.quiz-result.show { display: block; }
.quiz-result.good { background: rgba(42,140,64,.1);  border: 1px solid rgba(42,140,64,.25); }
.quiz-result.bad  { background: rgba(201,64,48,.08); border: 1px solid rgba(201,64,48,.22); }
.result-title { font-size: .92rem; font-weight: 800; margin-bottom: 6px; }
.result-title.good { color: #1a6c2c; }
.result-title.bad  { color: #8a2010; }
.result-explication { font-size: .84rem; color: var(--text-mid); line-height: 1.55; }
.result-xp {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(201,150,42,.15);
  color: #8a6000;
  font-size: .8rem;
  font-weight: 800;
  padding: 4px 12px;
  border-radius: 999px;
  margin-top: 10px;
}

/* LOGIN NOTICE */
.login-notice {
  background: rgba(18,49,78,.05);
  border: 1px solid rgba(18,49,78,.12);
  border-radius: 12px;
  padding: 16px 20px;
  font-size: .88rem;
  color: var(--text-mid);
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.login-notice a { font-weight: 800; color: var(--navy-dark); text-decoration: none; }
.login-notice a:hover { text-decoration: underline; }

/* ALREADY ANSWERED */
.already-done {
  background: rgba(42,140,64,.08);
  border: 1px solid rgba(42,140,64,.2);
  border-radius: 12px;
  padding: 14px 18px;
  font-size: .88rem;
  color: #1a5c28;
  font-weight: 600;
}

/* BOUTON SUIVANT */
.btn-next {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #7a5010, #c9962a);
  color: #fff;
  font-size: .9rem;
  font-weight: 800;
  padding: 13px 24px;
  border-radius: 10px;
  text-decoration: none;
  transition: opacity .18s;
  margin-top: 16px;
}
.btn-next:hover { opacity: .88; }

/* EMPTY QUIZ */
.empty-quiz {
  background: #fff;
  border-radius: 16px;
  border: 1px dashed rgba(139,90,43,.2);
  padding: 56px 24px;
  text-align: center;
  margin-bottom: 48px;
}
.empty-quiz-icon { font-size: 3.2rem; margin-bottom: 14px; }
.empty-quiz h3   { font-size: 1rem; font-weight: 800; color: var(--navy-dark); margin-bottom: 6px; }
.empty-quiz p    { font-size: .84rem; color: var(--text-muted); }

/* STATS */
.ktc-stats-card {
  background: #fff;
  border-radius: 16px;
  padding: 28px 32px;
  box-shadow: 0 2px 14px rgba(0,0,0,.07);
  margin-bottom: 48px;
  border: 1px solid rgba(139,90,43,.1);
}
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.stat-box {
  text-align: center;
  padding: 18px 12px;
  background: #f8f3ec;
  border-radius: 12px;
}
.stat-val {
  font-size: 1.8rem;
  font-weight: 900;
  color: var(--navy-dark);
  line-height: 1;
  margin-bottom: 4px;
  display: block;
}
.stat-lbl {
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--text-muted);
}
.stat-box.gold .stat-val { color: #9a6800; }

/* CATÉGORIES — EXPLORER LES MYSTÈRES */
.cats-section-title {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: #1a2d3e;
  margin-bottom: 28px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(18,49,78,.12);
}
.cats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.cat-card {
  background: #fff;
  border-radius: 14px;
  padding: 22px 18px 18px;
  text-align: center;
  border: 1px solid rgba(139,90,43,.1);
  box-shadow: 0 2px 10px rgba(0,0,0,.05);
  cursor: pointer;
  text-decoration: none;
  transition: transform .2s, box-shadow .2s;
  display: block;
}
.cat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(139,90,43,.15); }
.cat-emoji  { font-size: 2.2rem; margin-bottom: 10px; display: block; }
.cat-name   { font-size: .88rem; font-weight: 800; color: var(--navy-dark); margin-bottom: 6px; line-height: 1.3; }
.cat-desc   { font-size: .72rem; color: var(--text-muted); line-height: 1.45; margin-bottom: 10px; }
.cat-count  { font-size: .7rem; font-weight: 700; color: #9a6800; background: rgba(201,150,42,.12); padding: 3px 10px; border-radius: 999px; }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (min-width: 600px) {
  .quiz-options { grid-template-columns: 1fr 1fr; }
  .stats-grid   { grid-template-columns: repeat(3, 1fr); }
  .cats-grid    { grid-template-columns: repeat(3, 1fr); }
}
@media (min-width: 900px) {
  .cats-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
  .quiz-card-body { padding: 28px 32px 32px; }
}
@media (max-width: 599px) {
  .ktc-hero { padding: 100px 0 56px; }
  .ktc-hero::before { display: none; }
}
</style>';

// ── Scripts page ──────────────────────────────────────────────
// ATTENTION : tout le JS AJAX de ajax/ktc-answer.php est conservé intact.
$page_scripts = '<script>
(function() {
  var CSRF       = ' . json_encode($csrf) . ';
  var questionId = ' . ($question ? (int)$question['id'] : 0) . ';

  function submitAnswer(letter) {
    if (!questionId) return;
    var btns = document.querySelectorAll(".quiz-option-btn");
    btns.forEach(function(b) { b.disabled = true; });
    var selected = document.querySelector("[data-letter=\"" + letter + "\"]");
    if (selected) selected.classList.add("selected");

    var form = new FormData();
    form.append("question_id", questionId);
    form.append("answer", letter);
    form.append("csrf_token", CSRF);

    fetch("ajax/ktc-answer.php", {
      method: "POST",
      body: form,
      credentials: "same-origin"
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.error) { showResult(false, data.error, null, null); return; }
      btns.forEach(function(b) {
        var l = b.getAttribute("data-letter");
        if (l === data.correct_answer) b.classList.add("revealed-correct");
      });
      if (selected) {
        selected.classList.remove("selected");
        selected.classList.add(data.is_correct ? "correct" : "incorrect");
      }
      showResult(data.is_correct, null, data.explanation, data.xp_earned);
    })
    .catch(function() {
      showResult(false, "Une erreur est survenue. Réessaie.", null, null);
    });
  }

  function showResult(ok, errMsg, explanation, xp) {
    var panel = document.getElementById("quiz-result-panel");
    if (!panel) return;
    panel.classList.remove("good", "bad");
    panel.innerHTML = "";

    var t = document.createElement("div");
    t.className = "result-title";

    if (errMsg) {
      panel.classList.add("bad");
      t.classList.add("bad");
      t.textContent = errMsg;
      panel.appendChild(t);
    } else if (ok) {
      panel.classList.add("good");
      t.classList.add("good");
      t.textContent = "🎉 Bonne réponse !";
      panel.appendChild(t);
      if (explanation) {
        var e1 = document.createElement("div");
        e1.className = "result-explication";
        e1.textContent = explanation;
        panel.appendChild(e1);
      }
      if (xp) {
        var x1 = document.createElement("div");
        x1.className = "result-xp";
        x1.textContent = "⚡ +" + xp + " XP gagnés !";
        panel.appendChild(x1);
      }
    } else {
      panel.classList.add("bad");
      t.classList.add("bad");
      t.textContent = "✗ Pas cette fois…";
      panel.appendChild(t);
      if (explanation) {
        var e2 = document.createElement("div");
        e2.className = "result-explication";
        e2.textContent = explanation;
        panel.appendChild(e2);
      }
    }

    panel.classList.add("show");
    var nextBtn = document.getElementById("btn-next-question");
    if (nextBtn) nextBtn.style.display = "inline-flex";
  }

  // Connexion requise — message au clic
  function requireLogin() {
    var notice = document.getElementById("login-notice");
    if (notice) {
      notice.style.outline = "2px solid #c9962a";
      notice.scrollIntoView({ behavior: "smooth", block: "nearest" });
      setTimeout(function() { notice.style.outline = ""; }, 1800);
    }
  }

  window.ktcSubmit      = submitAnswer;
  window.ktcRequireLogin = requireLogin;
})();
</script>';

require 'includes/header.php';
require 'includes/nav.php';
?>

<!-- HERO — Mystère de la Saison -->
<section class="ktc-hero">
  <div class="container">
    <div class="ktc-hero-inner">
      <div class="ktc-hero-season-label">LE MYSTÈRE DE LA SAISON</div>
      <h1>🥐 Ketokolé Tché !</h1>
      <p class="ktc-hero-sub">Chaque saison, un objet étrange apparaît dans la Zone. À vous de découvrir son origine, son histoire, ses secrets vendéens.</p>
    </div>
  </div>
</section>

<!-- CONTENU -->
<section class="ktc-section">
  <div class="container">

    <!-- MYSTÈRE DU MOMENT (ex "Question du moment") -->
    <div class="section-title-mystere">Mystère du moment</div>

    <?php if ($question && !empty($options)): ?>
    <div class="ktc-quiz-card">
      <div class="quiz-card-header">
        <div>
          <?php if (!empty($question['category'])): ?>
          <span class="quiz-category-badge"><?= e($question['category']) ?></span>
          <?php endif; ?>
          <?php if (!empty($question['difficulty'])): ?>
          <div class="quiz-difficulty" style="margin-top:8px">
            Difficulté&nbsp;: <span><?= _difficulty_stars((int)$question['difficulty']) ?></span>
          </div>
          <?php endif; ?>
          <div class="quiz-question-text">
            <?= e($question['question_text'] ?? $question['question'] ?? '') ?>
          </div>
        </div>
        <?php if (!empty($question['xp_reward'])): ?>
        <span style="background:rgba(201,150,42,.2);border:1px solid rgba(201,150,42,.35);color:#d4a830;font-size:.75rem;font-weight:800;padding:5px 12px;border-radius:999px;white-space:nowrap;align-self:flex-start">
          ⚡ +<?= format_xp((int)$question['xp_reward']) ?>
        </span>
        <?php endif; ?>
      </div>

      <div class="quiz-card-body">

        <?php if ($already_answered): ?>
        <div class="already-done">
          ✓ Tu as déjà répondu à cette question. Clique sur "Question suivante" pour en découvrir une autre.
        </div>

        <?php elseif ($is_logged): ?>
        <div class="quiz-options">
          <?php foreach ($options as $letter => $text): ?>
          <button
            class="quiz-option-btn"
            data-letter="<?= htmlspecialchars((string)$letter, ENT_QUOTES, 'UTF-8') ?>"
            onclick="window.ktcSubmit('<?= htmlspecialchars((string)$letter, ENT_QUOTES, 'UTF-8') ?>')"
          >
            <span class="quiz-opt-letter"><?= htmlspecialchars((string)$letter, ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= e((string)$text) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <div id="quiz-result-panel" class="quiz-result"></div>

        <?php else: ?>
        <!-- Non connecté : options visibles, validation impossible -->
        <div class="quiz-options">
          <?php foreach ($options as $letter => $text): ?>
          <button
            class="quiz-option-btn"
            data-letter="<?= htmlspecialchars((string)$letter, ENT_QUOTES, 'UTF-8') ?>"
            onclick="window.ktcRequireLogin()"
          >
            <span class="quiz-opt-letter"><?= htmlspecialchars((string)$letter, ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= e((string)$text) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <div class="login-notice" id="login-notice">
          <span>🔒</span>
          <span>
            <a href="login.php">Connecte-toi</a> pour valider ta réponse et gagner des XP —
            ou <a href="inscription.php">rejoins la Zone</a> si tu n'as pas encore de compte.
          </span>
        </div>
        <?php endif; ?>

        <a href="ktc.php" class="btn-next" id="btn-next-question"
          <?= ($already_answered ? '' : 'style="display:none"') ?>>
          Question suivante →
        </a>

      </div>
    </div>

    <?php else: ?>
    <div class="empty-quiz">
      <div class="empty-quiz-icon">🥐</div>
      <h3>Aucun mystère disponible pour l'instant</h3>
      <p>Les mystères arrivent bientôt. Reviens dans la Zone !</p>
    </div>
    <?php endif; ?>

    <!-- STATS (si connecté et au moins une réponse) -->
    <?php if ($is_logged && $user_stats['total'] > 0): ?>
    <div class="section-title">Ma progression KTC</div>
    <div class="ktc-stats-card">
      <div class="stats-grid">
        <div class="stat-box">
          <span class="stat-val"><?= $user_stats['correct'] ?> / <?= $user_stats['total'] ?></span>
          <span class="stat-lbl">Bonnes réponses</span>
        </div>
        <div class="stat-box">
          <span class="stat-val">
            <?= $user_stats['total'] > 0 ? round($user_stats['correct'] / $user_stats['total'] * 100) : 0 ?>%
          </span>
          <span class="stat-lbl">Taux de réussite</span>
        </div>
        <div class="stat-box gold">
          <span class="stat-val"><?= format_xp($user_stats['xp']) ?></span>
          <span class="stat-lbl">XP KTC gagnés</span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- EXPLORER LES MYSTÈRES (ex "Catégories") -->
    <div class="cats-section-title">Explorer les Mystères</div>
    <div class="cats-grid">
      <?php foreach ($categories as $cat): ?>
      <a href="ktc.php?cat=<?= urlencode($cat['slug']) ?>" class="cat-card">
        <span class="cat-emoji"><?= $cat['emoji'] ?></span>
        <div class="cat-name"><?= e($cat['label']) ?></div>
        <div class="cat-desc"><?= e($cat['desc']) ?></div>
        <?php $cnt = $cat_counts[$cat['slug']] ?? 0; ?>
        <?php if ($cnt > 0): ?>
        <span class="cat-count"><?= $cnt ?> mystère<?= $cnt > 1 ? 's' : '' ?></span>
        <?php else: ?>
        <span class="cat-count" style="opacity:.4">Bientôt</span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<?php require 'includes/footer.php'; ?>
