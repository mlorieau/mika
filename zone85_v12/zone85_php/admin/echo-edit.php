<?php
// ============================================================
// ZONE85 — Admin : Les Échos — Créer / Éditer un article
// ============================================================
$admin_current    = 'echos';
$admin_page_title = 'Les Échos — Éditeur';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();
$pdo = db();

// ── Rubriques définitives ─────────────────────────────────────
$rubrique_options = [
    'les-invisibles' => 'Les Invisibles',
    'deux-minutes'   => "T'as deux minutes\xc2\xa0?",
    'les-ovnis'      => 'Les OVNIS de la Zone85',
    'actualite'      => 'Actualité',
    'chemins'        => 'Sur les Chemins',
    'evenements'     => 'Événements',
];

// ── Auto-migration : galerie, cadrage images + blocs éditoriaux ─────────
if ($pdo) {
    try {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM articles")->fetchAll(), 'Field');
        if (!in_array('gallery', $cols)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN gallery JSON NULL AFTER cover_image");
        }
        if (!in_array('video_url', $cols)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN video_url VARCHAR(500) NULL AFTER gallery");
        }
        if (!in_array('thumb_image', $cols)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN thumb_image VARCHAR(500) NULL AFTER cover_image");
        }
        if (!in_array('cover_position', $cols)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN cover_position VARCHAR(50) NULL AFTER thumb_image");
        }
        if (!in_array('thumb_position', $cols)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN thumb_position VARCHAR(50) NULL AFTER cover_position");
        }
        if (!in_array('content_blocks', $cols)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN content_blocks JSON NULL AFTER body");
        }
    } catch (PDOException $e) { /* table absente */ }
}

// ── Chargement article existant ────────────────────────────────
$id      = (int)($_GET['id'] ?? 0);
$article = null;
if ($id > 0 && $pdo) {
    try {
        $s = $pdo->prepare('SELECT * FROM articles WHERE id=:id LIMIT 1');
        $s->execute([':id' => $id]);
        $article = $s->fetch() ?: null;
    } catch (PDOException $e) {}
}
$is_edit          = $article !== null;
$admin_page_title = $is_edit ? 'Éditer — ' . $article['title'] : 'Nouvel article';

$flash      = '';
$flash_type = 'ok';
if (!empty($_GET['saved'])) {
    $flash = 'Article enregistré avec succès.';
}

// ── Saisons disponibles ───────────────────────────────────────
$seasons_list = [];
if ($pdo) {
    try {
        $seasons_list = $pdo->query("SELECT id, title, status FROM seasons ORDER BY id DESC")->fetchAll();
    } catch (PDOException $e) {}
}

// handle_cover_upload() supprimé — remplacé par upload_editorial_image('echos') ci-dessous

// ── Traitement POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $flash = 'Jeton CSRF invalide. Formulaire rejeté.';
        $flash_type = 'err';
    } else {
        $f_title     = safe_input($_POST['title']       ?? '', 255);
        $f_slug      = safe_input($_POST['slug']        ?? '', 255);
        $f_rubrique  = safe_input($_POST['rubrique']    ?? 'actualite', 50);
        $f_author    = safe_input($_POST['author_name'] ?? 'Équipe Zone85', 100);
        $f_excerpt   = safe_input($_POST['excerpt']     ?? '', 500);
        $f_body      = $_POST['body']        ?? '';  // HTML Quill, non échappé
        $f_cover     = safe_input($_POST['cover_image'] ?? '', 500);
        $f_thumb     = safe_input($_POST['thumb_image'] ?? '', 500);
        $f_cover_pos = safe_input($_POST['cover_position'] ?? 'center center', 50) ?: 'center center';
        $f_thumb_pos = safe_input($_POST['thumb_position'] ?? 'center center', 50) ?: 'center center';
        $f_blocks    = $_POST['content_blocks_json'] ?? '[]';
        $f_gallery   = $_POST['gallery_json'] ?? '[]';
        $f_video_url = safe_input($_POST['video_url']   ?? '', 500);
        $f_status    = in_array($_POST['status'] ?? '', ['draft', 'published'], true)
                          ? $_POST['status'] : 'draft';
        $f_pub_at    = $_POST['published_at'] ?? '';
        $f_season_id = !empty($_POST['season_id']) ? (int)$_POST['season_id'] : null;

        // Cover upload (écrase l'URL si un fichier est fourni)
        $uploaded_cover = null;
        if (!empty($_FILES['cover_file']['tmp_name']) && ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $_cover_r = upload_editorial_image($_FILES['cover_file'], 'echos');
            if ($_cover_r['ok']) $uploaded_cover = $_cover_r['path'];
        }
        if ($uploaded_cover !== null) $f_cover = $uploaded_cover;

        // Nettoyage slug
        $f_slug = strtolower($f_slug);
        $f_slug = preg_replace('/[^a-z0-9\-]/', '', $f_slug);
        $f_slug = trim($f_slug, '-');
        if (empty($f_slug)) $f_slug = 'article-' . time();

        if (!array_key_exists($f_rubrique, $rubrique_options)) $f_rubrique = 'actualite';

        // Valider JSON galerie
        $gallery_arr = json_decode($f_gallery, true);
        if (!is_array($gallery_arr)) $f_gallery = '[]';

        // Valider JSON blocs éditoriaux
        $blocks_arr = json_decode($f_blocks, true);
        if (!is_array($blocks_arr)) $f_blocks = '[]';

        // Date de publication
        $published_at = null;
        if ($f_status === 'published') {
            if ($f_pub_at) {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $f_pub_at);
                $published_at = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            } else {
                $published_at = date('Y-m-d H:i:s');
            }
        }

        if (empty($f_title)) {
            $flash = 'Le titre est obligatoire.';
            $flash_type = 'err';
        } else {
            try {
                $gallery_val   = ($f_gallery !== '[]') ? $f_gallery : null;
                $blocks_val    = ($f_blocks !== '[]') ? $f_blocks : null;
                $video_val     = $f_video_url ?: null;

                if ($is_edit) {
                    $s = $pdo->prepare('
                        UPDATE articles SET
                            title=:title, slug=:slug, rubrique=:rub, author_name=:author,
                            excerpt=:excerpt, body=:body, cover_image=:cover,
                            thumb_image=:thumb, cover_position=:cover_pos, thumb_position=:thumb_pos,
                            content_blocks=:blocks, gallery=:gallery, video_url=:video,
                            status=:status, published_at=:pub, season_id=:season
                        WHERE id=:id
                    ');
                    $s->execute([
                        ':title'   => $f_title,   ':slug'    => $f_slug,
                        ':rub'     => $f_rubrique, ':author'  => $f_author,
                        ':excerpt' => $f_excerpt ?: null, ':body' => $f_body ?: null,
                        ':cover'   => $f_cover ?: null,
                        ':thumb'   => $f_thumb ?: null,
                        ':cover_pos' => $f_cover_pos ?: 'center center',
                        ':thumb_pos' => $f_thumb_pos ?: 'center center',
                        ':blocks'  => $blocks_val,
                        ':gallery' => $gallery_val, ':video'  => $video_val,
                        ':status'  => $f_status,   ':pub'    => $published_at,
                        ':season'  => $f_season_id, ':id'    => $article['id'],
                    ]);
                    $saved_id = $article['id'];
                } else {
                    $s = $pdo->prepare('
                        INSERT INTO articles
                            (title, slug, rubrique, season_id, author_name, excerpt, body,
                             cover_image, thumb_image, cover_position, thumb_position, content_blocks,
                             gallery, video_url, status, published_at)
                        VALUES
                            (:title, :slug, :rub, :season, :author, :excerpt, :body,
                             :cover, :thumb, :cover_pos, :thumb_pos, :blocks,
                             :gallery, :video, :status, :pub)
                    ');
                    $s->execute([
                        ':title'   => $f_title,   ':slug'    => $f_slug,
                        ':rub'     => $f_rubrique, ':season'  => $f_season_id,
                        ':author'  => $f_author,
                        ':excerpt' => $f_excerpt ?: null, ':body' => $f_body ?: null,
                        ':cover'   => $f_cover ?: null,
                        ':thumb'   => $f_thumb ?: null,
                        ':cover_pos' => $f_cover_pos ?: 'center center',
                        ':thumb_pos' => $f_thumb_pos ?: 'center center',
                        ':blocks'  => $blocks_val,
                        ':gallery' => $gallery_val, ':video'  => $video_val,
                        ':status'  => $f_status,   ':pub'    => $published_at,
                    ]);
                    $saved_id = (int)$pdo->lastInsertId();
                }
                $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                header('Location: ' . $base . '/admin/echo-edit.php?id=' . $saved_id . '&saved=1');
                exit;
            } catch (PDOException $e) {
                $flash = 'Erreur de sauvegarde : ' . e($e->getMessage());
                $flash_type = 'err';
            }
        }
    }
}

// ── Valeurs formulaire ────────────────────────────────────────
$v_title     = $_POST['title']       ?? ($article['title']       ?? '');
$v_slug      = $_POST['slug']        ?? ($article['slug']        ?? '');
$v_rubrique  = $_POST['rubrique']    ?? ($article['rubrique']    ?? 'actualite');
$v_season_id = isset($_POST['season_id']) ? (int)$_POST['season_id']
             : (int)($article['season_id'] ?? 0);
$v_author    = $_POST['author_name'] ?? ($article['author_name'] ?? 'Équipe Zone85');
$v_excerpt   = $_POST['excerpt']     ?? ($article['excerpt']     ?? '');
$v_body      = $_POST['body']        ?? ($article['body']        ?? '');
$v_cover     = $_POST['cover_image'] ?? ($article['cover_image'] ?? '');
$v_thumb     = $_POST['thumb_image'] ?? ($article['thumb_image'] ?? '');
$v_cover_pos = $_POST['cover_position'] ?? ($article['cover_position'] ?? 'center center');
$v_thumb_pos = $_POST['thumb_position'] ?? ($article['thumb_position'] ?? 'center center');
$v_blocks    = json_decode($_POST['content_blocks_json'] ?? ($article['content_blocks'] ?? '[]'), true);
if (!is_array($v_blocks)) $v_blocks = [];
$v_gallery   = json_decode($article['gallery'] ?? '[]', true);
if (!is_array($v_gallery)) $v_gallery = [];
$v_video_url = $_POST['video_url']   ?? ($article['video_url']  ?? '');
$v_status    = $_POST['status']      ?? ($article['status']      ?? 'draft');
$v_pub_at    = '';
if (!empty($article['published_at'])) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $article['published_at']);
    if ($dt) $v_pub_at = $dt->format('Y-m-d\TH:i');
}
if (!empty($_POST['published_at'])) $v_pub_at = $_POST['published_at'];

$csrf_val        = e(csrf_token());
$v_gallery_json  = json_encode($v_gallery, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$v_blocks_json   = json_encode($v_blocks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$v_body_js       = json_encode($v_body);
$base_url_js     = json_encode(defined('BASE_URL') ? rtrim(BASE_URL, '/') : '');

$admin_scripts = <<<SCRIPTS
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
// ── Slugify ────────────────────────────────────────────────────
function slugify(t){
    var m={'à':'a','â':'a','ä':'a','á':'a','ã':'a','è':'e','é':'e','ê':'e','ë':'e',
           'î':'i','ï':'i','ì':'i','ô':'o','ö':'o','ò':'o','ù':'u','û':'u','ü':'u',
           'ç':'c','ñ':'n','œ':'oe','æ':'ae'};
    return t.toLowerCase()
        .replace(/[àâäáãèéêëîïìôöòùûüçñœæ]/g,function(c){return m[c]||c;})
        .replace(/[^a-z0-9\s\-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
}
var slugLocked = document.getElementById('f_slug').value.length > 0;
document.getElementById('f_title').addEventListener('input',function(){
    if(!slugLocked) document.getElementById('f_slug').value=slugify(this.value);
});
document.getElementById('f_slug').addEventListener('input',function(){
    slugLocked = this.value.length > 0;
});
document.getElementById('f_slug').addEventListener('blur',function(){
    this.value = slugify(this.value);
});

// ── Compteur extrait ───────────────────────────────────────────
var excerptEl = document.getElementById('f_excerpt');
var cntEl     = document.getElementById('excerpt_count');
function updateCount(){
    var l = excerptEl.value.length;
    cntEl.textContent = l+'/150';
    cntEl.style.color = l > 150 ? '#c0392b' : '#6b7f96';
}
excerptEl.addEventListener('input', updateCount);
updateCount();

// ── Aperçu couverture ──────────────────────────────────────────
document.getElementById('cover_file_input').addEventListener('change', function(){
    if(this.files[0]){
        var r = new FileReader();
        r.onload = function(e){
            var p = document.getElementById('cover_preview');
            p.src = e.target.result;
            p.style.display = 'block';
        };
        r.readAsDataURL(this.files[0]);
    }
});

// ── Galerie ────────────────────────────────────────────────────
var gallery = {$v_gallery_json};
var blocks = {$v_blocks_json};
var baseUrl = {$base_url_js};

function renderGallery(){
    var c = document.getElementById('gallery_grid');
    if(gallery.length === 0){
        c.innerHTML = '<p style="color:#aaa;font-size:.82rem;padding:8px 0">Aucune photo dans la galerie.</p>';
    } else {
        c.innerHTML = gallery.map(function(p,i){
            var src = p.startsWith('http') ? p : baseUrl+'/'+p;
            return '<div style="position:relative;display:inline-block;margin:4px">'
                +'<img src="'+src+'" style="width:110px;height:80px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,.08)" loading="lazy">'
                +'<button type="button" onclick="removeGallery('+i+')" '
                +'style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;'
                +'background:#c0392b;color:#fff;border:none;cursor:pointer;font-size:.7rem;line-height:1;display:flex;align-items:center;justify-content:center">'
                +'✕</button></div>';
        }).join('');
    }
    document.getElementById('gallery_json').value = JSON.stringify(gallery);
}

function removeGallery(i){
    if(confirm('Retirer cette photo de la galerie ?')){
        gallery.splice(i,1);
        renderGallery();
    }
}

document.getElementById('gallery_file_input').addEventListener('change',function(){
    Array.from(this.files).forEach(uploadGalleryFile);
    this.value = '';
});

function uploadGalleryFile(file){
    var fd = new FormData();
    fd.append('file', file);
    fd.append('csrf_token', '{$csrf_val}');
    var btn = document.getElementById('gallery_upload_btn');
    if(btn) btn.textContent = '⏳ Upload…';
    fetch(baseUrl+'/ajax/echo-upload.php', {method:'POST', body:fd})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.ok){
                gallery.push(d.path);
                renderGallery();
            } else {
                alert('Erreur upload : '+(d.error||'inconnue'));
            }
            if(btn) btn.textContent = '📷 Ajouter des photos';
        })
        .catch(function(){
            alert('Erreur réseau lors de l\'upload.');
            if(btn) btn.textContent = '📷 Ajouter des photos';
        });
}


renderGallery();

// ── Blocs éditoriaux des Échos ────────────────────────────────
function escHtml(s){return String(s||'').replace(/[&<>\"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]||c;});}
function blockLabel(t){return {text:'Texte enrichi',image:'Image seule',gallery:'Galerie',quote:'Citation',heading:'Intertitre',note:'Encart'}[t]||'Bloc';}
function renderBlocks(){
  var wrap=document.getElementById(‘echo_blocks_list’);
  var hidden=document.getElementById(‘content_blocks_json’);
  if(!wrap || !hidden) return;
  hidden.value=JSON.stringify(blocks||[]);
  if(!blocks || !blocks.length){
    wrap.innerHTML=’<p style="color:#8a98a8;font-size:.86rem;margin:0;padding:10px 0">Aucun bloc ajouté. Si vide, l\’article utilise le corps principal ci-dessus.</p>’;
    return;
  }
  wrap.innerHTML=blocks.map(function(b,i){
    var t=b.type||’text’;
    var extra=’’;
    if(t===’text’) extra=’<div style="border:1.5px solid #dde3ec;border-radius:8px;overflow:hidden;background:#fff"><div data-i="’+i+’" class="blk-quill-editor"></div></div>’;
    if(t===’image’) extra=’’
      +’<div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">’
      +’<label style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;padding:9px 14px;background:#f0ece7;border:1.5px solid #d0cbc5;border-radius:8px;font-size:.78rem;font-weight:700;color:#3d5166;white-space:nowrap">📁 Choisir une photo<input type="file" accept=".jpg,.jpeg,.png,.webp" style="display:none" class="blk-img-up" data-bi="’+i+’" data-bk="src"></label>’
      +(b.src?’<span style="font-size:.74rem;color:#6b7f96;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px">’+escHtml(b.src.split(‘/’).pop())+’</span>’:’<span style="font-size:.74rem;color:#aaa">Aucune photo</span>’)
      +’</div>’
      +(b.src?’<div style="margin-bottom:10px"><img src="’+(b.src.startsWith(‘http’)?b.src:baseUrl+’/’+b.src)+’" style="max-height:140px;border-radius:8px;object-fit:cover;border:1px solid rgba(0,0,0,.08)" loading="lazy"></div>’:’’)
      +’<input data-i="’+i+’" data-k="caption" class="adm-input block-field" placeholder="Légende" value="’+escHtml(b.caption||’’)+’" style="margin-bottom:8px">’
      +’<input data-i="’+i+’" data-k="position" class="adm-input block-field" placeholder="Cadrage (ex: center top, 50% 30%)" value="’+escHtml(b.position||’center center’)+’">’;
    if(t===’gallery’) extra=’’
      +’<label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;padding:8px 13px;background:#f0ece7;border:1.5px solid #d0cbc5;border-radius:8px;font-size:.78rem;font-weight:700;color:#3d5166;margin-bottom:10px">📷 Ajouter des photos<input type="file" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none" class="blk-gal-up" data-bi="’+i+’"></label>’
      +(b.images&&b.images.length?’<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">’+b.images.map(function(src,si){var u=src.startsWith(‘http’)?src:baseUrl+’/’+src;return ‘<div style="position:relative"><img src="’+u+’" style="width:80px;height:60px;object-fit:cover;border-radius:5px;border:1px solid rgba(0,0,0,.08)" loading="lazy"><button type="button" onclick="removeBlockImg(‘+i+’,’+si+’)" style="position:absolute;top:-5px;right:-5px;width:18px;height:18px;border-radius:50%;background:#c0392b;color:#fff;border:none;cursor:pointer;font-size:.6rem;line-height:1;display:flex;align-items:center;justify-content:center">✕</button></div>’;}).join(‘’)+’</div>’:’’)
      +’<input data-i="’+i+’" data-k="caption" class="adm-input block-field" placeholder="Légende de galerie" value="’+escHtml(b.caption||’’)+’">’;
    if(t===’quote’) extra=’<textarea data-i="’+i+’" data-k="text" rows="3" class="adm-textarea block-field" placeholder="Citation">’+escHtml(b.text||’’)+’</textarea><input data-i="’+i+’" data-k="author" class="adm-input block-field" placeholder="Auteur / source" value="’+escHtml(b.author||’’)+’" style="margin-top:8px">’;
    if(t===’heading’) extra=’<input data-i="’+i+’" data-k="text" class="adm-input block-field" placeholder="Intertitre" value="’+escHtml(b.text||’’)+’">’;
    if(t===’note’) extra=’<input data-i="’+i+’" data-k="title" class="adm-input block-field" placeholder="Titre de l\’encart" value="’+escHtml(b.title||’’)+’"><textarea data-i="’+i+’" data-k="text" rows="3" class="adm-textarea block-field" placeholder="Contenu de l\’encart" style="margin-top:8px">’+escHtml(b.text||’’)+’</textarea>’;
    return ‘<div class="echo-block-admin" style="border:1px solid #d6dde6;border-radius:10px;padding:14px;margin:12px 0;background:#fff">’
      +’<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px"><strong style="color:#0f1e2d">’+(i+1)+’. ‘+blockLabel(t)+’</strong><div style="display:flex;gap:6px"><button type="button" class="btn-adm btn-adm-light" onclick="moveBlock(‘+i+’,-1)">↑</button><button type="button" class="btn-adm btn-adm-light" onclick="moveBlock(‘+i+’,1)">↓</button><button type="button" class="btn-adm btn-adm-danger" onclick="removeBlock(‘+i+’)">Supprimer</button></div></div>’
      +extra+’</div>’;
  }).join(‘’);
  wrap.querySelectorAll(‘.block-field’).forEach(function(el){
    el.addEventListener(‘input’,function(){
      var i=parseInt(this.getAttribute(‘data-i’),10), k=this.getAttribute(‘data-k’);
      if(!blocks[i]) return;
      blocks[i][k]=this.value;
      hidden.value=JSON.stringify(blocks||[]);
    });
  });
  wrap.querySelectorAll(‘.blk-img-up’).forEach(function(el){
    el.addEventListener(‘change’,function(){
      if(!this.files[0]) return;
      var bi=parseInt(this.getAttribute(‘data-bi’),10), bk=this.getAttribute(‘data-bk’);
      var lbl=this.closest(‘label’); if(lbl) lbl.textContent=’⏳ Upload…’;
      var fd=new FormData(); fd.append(‘file’,this.files[0]); fd.append(‘csrf_token’,’{$csrf_val}’);
      fetch(baseUrl+’/ajax/echo-upload.php’,{method:’POST’,body:fd})
        .then(function(r){return r.json();})
        .then(function(d){
          if(d.ok){blocks[bi][bk]=d.path;renderBlocks();}
          else{renderBlocks();alert(‘Erreur upload: ‘+(d.error||’inconnue’));}
        })
        .catch(function(){renderBlocks();alert(‘Erreur réseau’);});
    });
  });
  wrap.querySelectorAll(‘.blk-gal-up’).forEach(function(el){
    el.addEventListener(‘change’,function(){
      var files=Array.from(this.files); if(!files.length) return;
      var bi=parseInt(this.getAttribute(‘data-bi’),10);
      var pending=files.length;
      if(!blocks[bi].images) blocks[bi].images=[];
      files.forEach(function(file){
        var fd=new FormData(); fd.append(‘file’,file); fd.append(‘csrf_token’,’{$csrf_val}’);
        fetch(baseUrl+’/ajax/echo-upload.php’,{method:’POST’,body:fd})
          .then(function(r){return r.json();})
          .then(function(d){
            if(d.ok) blocks[bi].images.push(d.path);
            pending--; if(!pending) renderBlocks();
          })
          .catch(function(){pending--;if(!pending) renderBlocks();});
      });
    });
  });
  wrap.querySelectorAll(‘.blk-quill-editor’).forEach(function(el){
    var idx=parseInt(el.getAttribute(‘data-i’),10);
    var bq=new Quill(el,{
      modules:{toolbar:[[‘bold’,’italic’,’underline’,’strike’],[{header:[2,3,false]}],[‘blockquote’],[{list:’ordered’},{list:’bullet’}],[‘link’],[‘clean’]]},
      theme:’snow’
    });
    if(blocks[idx]&&blocks[idx].html) bq.clipboard.dangerouslyPasteHTML(blocks[idx].html);
    bq.on(‘text-change’,function(){blocks[idx].html=bq.root.innerHTML;hidden.value=JSON.stringify(blocks);});
  });
}
function removeBlockImg(bi,si){blocks[bi].images.splice(si,1);renderBlocks();}
function addEchoBlock(type){
  var b={type:type};
  if(type==='text') b.html='';
  if(type==='image') b={type:'image',src:'',caption:'',position:'center center'};
  if(type==='gallery') b={type:'gallery',images:[],caption:''};
  if(type==='quote') b={type:'quote',text:'',author:''};
  if(type==='heading') b={type:'heading',text:''};
  if(type==='note') b={type:'note',title:'À retenir',text:''};
  blocks.push(b); renderBlocks();
}
function removeBlock(i){ if(confirm('Supprimer ce bloc ?')){blocks.splice(i,1);renderBlocks();} }
function moveBlock(i,d){ var j=i+d; if(j<0||j>=blocks.length)return; var t=blocks[i];blocks[i]=blocks[j];blocks[j]=t;renderBlocks(); }
renderBlocks();

// ── Aperçu YouTube ─────────────────────────────────────────────
var ytInput = document.getElementById('f_video_url');
var ytPreview = document.getElementById('yt_preview');
var ytIframe  = document.getElementById('yt_iframe');

function updateYT(){
    var url = ytInput.value;
    var m   = url.match(/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_\-]{11})/);
    if(m){
        ytIframe.src = 'https://www.youtube.com/embed/'+m[1];
        ytPreview.style.display = 'block';
    } else {
        ytPreview.style.display = 'none';
        ytIframe.src = '';
    }
}
ytInput.addEventListener('input', updateYT);
if(ytInput.value) updateYT();

// ── Quill éditeur ──────────────────────────────────────────────
var quill = new Quill('#quill_editor', {
    modules: {
        toolbar: [
            ['bold','italic','underline','strike'],
            [{header:[2,3,false]}],
            ['blockquote'],
            [{list:'ordered'},{list:'bullet'}],
            ['link'],
            ['clean']
        ]
    },
    theme: 'snow'
});

// Charger le contenu existant
var bodyContent = {$v_body_js};
if(bodyContent) quill.clipboard.dangerouslyPasteHTML(bodyContent);

// Synchroniser avant soumission
document.getElementById('echo_form').addEventListener('submit',function(){
    document.getElementById('f_body').value = quill.root.innerHTML;
});

// Style Quill dans l'interface admin
var qlEl = document.querySelector('.ql-container');
if(qlEl){ qlEl.style.minHeight = '300px'; qlEl.style.fontSize = '1rem'; qlEl.style.fontFamily = 'Inter, sans-serif'; }

// ── Analyse SEO en direct ──────────────────────────────────────
var _seoTimer = null;

function _seoItem(status, label, detail) {
    var c = {green:'#1a7a42', orange:'#8a6020', red:'#c0392b'};
    var b = {green:'rgba(42,157,92,.1)', orange:'rgba(201,150,42,.09)', red:'rgba(234,86,73,.08)'};
    var i = {green:'✓', orange:'⚠', red:'✕'};
    return '<div style="display:flex;align-items:flex-start;gap:9px;padding:7px 10px;border-radius:7px;background:'+b[status]+'">'
         + '<span style="width:17px;height:17px;border-radius:50%;background:'+c[status]+';color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:900;flex-shrink:0;margin-top:1px">'+i[status]+'</span>'
         + '<div><div style="font-size:.82rem;font-weight:700;color:#0f1e2d">'+label+'</div>'
         + '<div style="font-size:.74rem;color:#6b7f96;margin-top:1px">'+detail+'</div></div></div>';
}

function runSEO() {
    var title   = (document.getElementById('f_title')   || {value:''}).value;
    var slug    = (document.getElementById('f_slug')    || {value:''}).value;
    var excerpt = (document.getElementById('f_excerpt') || {value:''}).value;
    var cover   = (document.getElementById('f_cover')   || {value:''}).value;
    var body    = quill ? quill.root.innerHTML : '';

    var items   = [];
    var statuses = [];

    // 1. Titre
    var tl = title.length;
    var ts = tl >= 50 && tl <= 60 ? 'green' : (tl >= 30 && tl <= 70 ? 'orange' : 'red');
    items.push(_seoItem(ts, 'Titre de l\'article', tl + ' caractères — idéal 50–60'));
    statuses.push(ts);

    // 2. Meta description (extrait)
    var el = excerpt.length;
    var es = el >= 120 && el <= 158 ? 'green' : (el >= 60 && el <= 200 ? 'orange' : 'red');
    items.push(_seoItem(es, 'Meta description (extrait)', el + ' caractères — idéal 120–158'));
    statuses.push(es);

    // 3. Slug
    var ss = (slug.length > 0 && slug.length <= 60) ? 'green' : (slug.length > 60 ? 'orange' : 'red');
    items.push(_seoItem(ss, 'URL Slug', slug.length > 0 ? '"'+slug+'" ('+slug.length+' chars)' : 'Slug manquant'));
    statuses.push(ss);

    // 4. Image couverture (og:image)
    var cs = cover.length > 0 ? 'green' : 'orange';
    items.push(_seoItem(cs, 'Image de couverture', cover.length > 0 ? 'Présente — og:image défini' : 'Absente — recommandé pour les partages'));
    statuses.push(cs);

    // 5. H2 dans le corps
    var h2 = (body.match(/<h2[^>]*>/gi) || []).length;
    var wordCount = body.replace(/<[^>]+>/g,'').trim().split(/\s+/).filter(Boolean).length;
    var hs = h2 >= 2 ? 'green' : (h2 === 1 ? 'orange' : (wordCount > 300 ? 'red' : 'orange'));
    items.push(_seoItem(hs, 'Sous-titres H2 dans le corps', h2 > 0 ? h2+' H2 trouvé(s)' : 'Aucun H2 — structurer le contenu'));
    statuses.push(hs);

    // 6. Alts images dans le corps
    var imgs = body.match(/<img[^>]*>/gi) || [];
    var withAlt = imgs.filter(function(im){ return /alt=["'][^"']+["']/i.test(im); }).length;
    var as2 = imgs.length === 0 ? 'green' : (withAlt === imgs.length ? 'green' : (withAlt > 0 ? 'orange' : 'red'));
    items.push(_seoItem(as2, 'Textes alternatifs images', imgs.length === 0 ? 'Aucune image (OK)' : withAlt+'/'+imgs.length+' images avec alt'));
    statuses.push(as2);

    // Score
    var pts = {green:2, orange:1, red:0};
    var total = statuses.reduce(function(s,st){ return s + pts[st]; }, 0);
    var pct   = Math.round(total / (statuses.length * 2) * 100);
    var scoreColor = pct >= 80 ? '#1a7a42' : (pct >= 50 ? '#8a6020' : '#c0392b');
    var scoreBg    = pct >= 80 ? 'rgba(42,157,92,.12)' : (pct >= 50 ? 'rgba(201,150,42,.12)' : 'rgba(234,86,73,.1)');

    var badge = document.getElementById('seo-score-badge');
    badge.textContent  = pct+'%';
    badge.style.background = scoreBg;
    badge.style.color  = scoreColor;
    document.getElementById('seo-checklist').innerHTML = items.join('');
}

// Init + live listeners
(function(){
    setTimeout(runSEO, 200);
    ['f_title','f_slug','f_excerpt','f_cover'].forEach(function(id){
        var el = document.getElementById(id);
        if(el) el.addEventListener('input', function(){ clearTimeout(_seoTimer); _seoTimer = setTimeout(runSEO, 400); });
    });
    if(quill) quill.on('text-change', function(){ clearTimeout(_seoTimer); _seoTimer = setTimeout(runSEO, 600); });
})();

// ── PageSpeed ─────────────────────────────────────────────────
function runPageSpeed() {
    var btn  = document.getElementById('pagespeed-btn');
    var res  = document.getElementById('pagespeed-result');
    var slug = (document.getElementById('f_slug') || {value:''}).value;
    if (!slug) { alert('Renseigne d\'abord le slug de l\'article.'); return; }

    var pageUrl = window.location.protocol + '//' + window.location.host + '/les-echos-article.php?slug=' + encodeURIComponent(slug);
    btn.textContent = '⏳ Analyse…';
    btn.disabled = true;
    res.innerHTML = '<p style="font-size:.78rem;color:#6b7f96;margin:0">Interrogation de Google PageSpeed Insights…</p>';

    fetch({$base_url_js} + '/admin/ajax/pagespeed.php?url=' + encodeURIComponent(pageUrl))
        .then(function(r){ return r.json(); })
        .then(function(d){
            btn.textContent = '⚡ Tester PageSpeed';
            btn.disabled = false;
            if (d.error) { res.innerHTML = '<p style="font-size:.78rem;color:#c0392b;margin:0">Erreur : ' + d.error + '</p>'; return; }
            res.innerHTML = _renderPS(d);
        })
        .catch(function(){
            btn.textContent = '⚡ Tester PageSpeed';
            btn.disabled = false;
            res.innerHTML = '<p style="font-size:.78rem;color:#c0392b;margin:0">Erreur réseau.</p>';
        });
}

function _renderPS(d) {
    function sc(s){ return s>=90?'#1a7a42':s>=50?'#8a6020':'#c0392b'; }
    function sb(s){ return s>=90?'rgba(42,157,92,.1)':s>=50?'rgba(201,150,42,.1)':'rgba(234,86,73,.08)'; }
    var m = Math.round((d.mobile||0)*100), dsk = Math.round((d.desktop||0)*100);
    var h = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">'
          + '<div style="background:'+sb(m)+';border-radius:8px;padding:10px;text-align:center"><div style="font-size:1.4rem;font-weight:900;color:'+sc(m)+'">'+m+'</div><div style="font-size:.68rem;font-weight:700;color:#6b7f96;margin-top:2px">📱 Mobile</div></div>'
          + '<div style="background:'+sb(dsk)+';border-radius:8px;padding:10px;text-align:center"><div style="font-size:1.4rem;font-weight:900;color:'+sc(dsk)+'">'+dsk+'</div><div style="font-size:.68rem;font-weight:700;color:#6b7f96;margin-top:2px">🖥 Desktop</div></div>'
          + '</div>';
    if (d.cwv && Object.keys(d.cwv).length > 0) {
        var labels = {lcp:'LCP',cls:'CLS',fcp:'FCP',ttfb:'TTFB',inp:'INP'};
        h += '<div style="font-size:.72rem;font-weight:700;color:#6b7f96;margin-bottom:5px">Core Web Vitals</div><div style="display:flex;flex-direction:column;gap:3px">';
        Object.keys(d.cwv).forEach(function(k){
            h += '<div style="display:flex;justify-content:space-between;font-size:.75rem;padding:4px 8px;background:#f8f4ef;border-radius:5px"><span style="color:#3d5166;font-weight:600">'+(labels[k]||k)+'</span><span style="font-weight:700">'+d.cwv[k]+'</span></div>';
        });
        h += '</div>';
    }
    return h;
}
</script>
SCRIPTS;

require_once __DIR__ . '/_admin-header.php';
?>

<!-- ── Breadcrumb ──────────────────────────────────────────── -->
<div style="margin-bottom:18px;font-size:.82rem;color:#6b7f96">
  <a href="echos.php" style="color:#6b7f96;text-decoration:none">← Les Échos</a>
  <span style="margin:0 8px">/</span>
  <?= $is_edit ? e($article['title']) : 'Nouvel article' ?>
</div>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">
      <?= $is_edit ? '✏️ Éditer l\'article' : '+ Nouvel article' ?>
    </h1>
    <?php if ($is_edit): ?>
      <p class="adm-page-sub">
        ID #<?= (int)$article['id'] ?> &mdash;
        <a href="<?= defined('BASE_URL') ? rtrim(BASE_URL, '/') : '' ?>/les-echos-article.php?slug=<?= urlencode($article['slug'] ?? '') ?>"
           target="_blank" style="color:#7ab8e8">Voir l'article ↗</a>
      </p>
    <?php endif; ?>
  </div>
  <div class="adm-page-actions">
    <a href="echos.php" class="btn-adm btn-adm-ghost">← Retour à la liste</a>
    <button form="echo_form" type="submit" class="btn-adm btn-adm-primary">
      <?= $is_edit ? '✓ Sauvegarder' : '+ Créer l\'article' ?>
    </button>
  </div>
</div>

<?php if ($flash): ?>
  <div class="adm-flash adm-flash-<?= e($flash_type) ?>">
    <?= e($flash) ?>
  </div>
<?php endif; ?>

<!-- ── Formulaire ──────────────────────────────────────────── -->
<form id="echo_form" method="post"
      action="echo-edit.php<?= $is_edit ? '?id=' . (int)$article['id'] : '' ?>"
      enctype="multipart/form-data">
  <?= csrf_field() ?>

  <!-- ── Informations principales ──────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Informations principales</p>
    <div class="adm-form-grid">

      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_title">Titre <span>*</span></label>
        <input id="f_title" type="text" name="title" class="adm-input"
               maxlength="255" required placeholder="Titre de l'article"
               value="<?= e($v_title) ?>">
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_slug">Slug (URL)</label>
        <input id="f_slug" type="text" name="slug" class="adm-input"
               maxlength="255" placeholder="généré-automatiquement"
               value="<?= e($v_slug) ?>">
        <span class="adm-hint">Généré depuis le titre. Modifiable manuellement.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_rubrique">Rubrique <span>*</span></label>
        <select id="f_rubrique" name="rubrique" class="adm-select">
          <?php foreach ($rubrique_options as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= $v_rubrique === $k ? 'selected' : '' ?>>
              <?= e($lbl) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_season">Saison associée</label>
        <select id="f_season" name="season_id" class="adm-select">
          <option value="">— Aucune saison —</option>
          <?php foreach ($seasons_list as $sea): ?>
            <option value="<?= (int)$sea['id'] ?>"
              <?= $v_season_id === (int)$sea['id'] ? 'selected' : '' ?>>
              <?= e($sea['title']) ?>
              <?= $sea['status'] === 'active' ? ' ● EN COURS' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_author">Auteur</label>
        <input id="f_author" type="text" name="author_name" class="adm-input"
               maxlength="100" value="<?= e($v_author) ?>">
      </div>

    </div>
  </div>

  <!-- ── Image de couverture ───────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Image de couverture</p>

    <?php if ($v_cover): ?>
    <div style="margin-bottom:14px">
      <img src="<?= e($v_cover) ?>" id="cover_preview" loading="lazy"
           style="max-width:100%;max-height:280px;border-radius:10px;object-fit:cover;display:block;border:1px solid rgba(0,0,0,.08)">
    </div>
    <?php else: ?>
    <img src="" id="cover_preview" style="display:none;max-width:100%;max-height:280px;border-radius:10px;object-fit:cover;margin-bottom:14px;border:1px solid rgba(0,0,0,.08)">
    <?php endif; ?>

    <div class="adm-form-grid">
      <div class="adm-field">
        <label class="adm-label">Uploader un nouveau fichier</label>
        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;padding:8px 14px;border:1.5px dashed #b8c9db;border-radius:8px;font-size:.82rem;font-weight:600;color:#4a6177">
          📁 Choisir une image
          <input type="file" id="cover_file_input" name="cover_file"
                 accept=".jpg,.jpeg,.png,.webp" style="display:none">
        </label>
        <span class="adm-hint">JPG, PNG, WebP — max <?= round(UPLOAD_MAX_SIZE_MEDIA / 1048576) ?> Mo. Écrase l'URL ci-dessous.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_cover">Ou URL externe</label>
        <input id="f_cover" type="text" name="cover_image" class="adm-input"
               placeholder="https://... ou assets/img/..."
               value="<?= e($v_cover) ?>">
        <span class="adm-hint">Laissez vide pour afficher le dégradé de la rubrique.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_thumb">Image vignette listing (optionnel)</label>
        <input id="f_thumb" type="text" name="thumb_image" class="adm-input"
               placeholder="Si vide, la couverture est utilisée"
               value="<?= e($v_thumb) ?>">
        <span class="adm-hint">Utile si l’image de couverture ne fonctionne pas bien en carré.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_cover_pos">Cadrage couverture</label>
        <input id="f_cover_pos" type="text" name="cover_position" class="adm-input"
               placeholder="center center, center top, 50% 30%..."
               value="<?= e($v_cover_pos ?: 'center center') ?>">
        <span class="adm-hint">Pilote le cadrage du hero article avec object-position.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_thumb_pos">Cadrage vignette</label>
        <input id="f_thumb_pos" type="text" name="thumb_position" class="adm-input"
               placeholder="center center, center top, 50% 30%..."
               value="<?= e($v_thumb_pos ?: 'center center') ?>">
        <span class="adm-hint">Pilote le cadrage des cartes carrées dans la liste des Échos.</span>
      </div>
    </div>
  </div>

  <!-- ── Chapeau / Extrait ─────────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Chapeau / Extrait</p>
    <div class="adm-field">
      <label class="adm-label" for="f_excerpt">Accroche (150 caractères max)</label>
      <textarea id="f_excerpt" name="excerpt" class="adm-textarea" rows="3"
                maxlength="150"
                placeholder="Résumé accrocheur affiché dans la liste des articles…"><?= e($v_excerpt) ?></textarea>
      <span class="adm-hint" id="excerpt_count">0/150</span>
    </div>
  </div>

  <!-- ── Corps de l'article ───────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Corps de l'article</p>
    <!-- Textarea cachée — synchronisée par Quill avant soumission -->
    <textarea id="f_body" name="body" style="display:none"><?= e($v_body) ?></textarea>
    <!-- Barre d'outils Quill + éditeur -->
    <div style="border:1px solid #d6dde6;border-radius:8px;overflow:hidden;background:#fff">
      <div id="quill_editor" style="min-height:300px;font-size:1rem"></div>
    </div>
    <span class="adm-hint" style="margin-top:6px;display:block">
      Barre d'outils : <strong>Gras</strong>, <em>Italique</em>, Souligné, Barré, H2/H3, Blockquote, Listes, Lien, Effacer la mise en forme.
    </span>
  </div>

  <!-- ── Blocs éditoriaux modulaires ───────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Blocs éditoriaux modulaires</p>
    <p class="adm-hint" style="margin-bottom:12px;display:block">
      Optionnel. Si tu ajoutes des blocs ici, ils seront affichés dans l’article à la place du corps principal classique. Pratique pour alterner texte, images, galerie, citation et encarts.
    </p>
    <input type="hidden" name="content_blocks_json" id="content_blocks_json" value="<?= e($v_blocks_json) ?>">
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
      <button type="button" class="btn-adm btn-adm-light" onclick="addEchoBlock('text')">+ Texte</button>
      <button type="button" class="btn-adm btn-adm-light" onclick="addEchoBlock('image')">+ Image</button>
      <button type="button" class="btn-adm btn-adm-light" onclick="addEchoBlock('gallery')">+ Galerie</button>
      <button type="button" class="btn-adm btn-adm-light" onclick="addEchoBlock('quote')">+ Citation</button>
      <button type="button" class="btn-adm btn-adm-light" onclick="addEchoBlock('heading')">+ Intertitre</button>
      <button type="button" class="btn-adm btn-adm-light" onclick="addEchoBlock('note')">+ Encart</button>
    </div>
    <div id="echo_blocks_list"></div>
  </div>

  <!-- ── Galerie photo ─────────────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Galerie photo</p>
    <input type="hidden" name="gallery_json" id="gallery_json" value="<?= e($v_gallery_json) ?>">
    <div id="gallery_grid" style="margin-bottom:14px"></div>
    <label id="gallery_upload_btn"
           style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;padding:8px 14px;border:1.5px dashed #b8c9db;border-radius:8px;font-size:.82rem;font-weight:600;color:#4a6177">
      📷 Ajouter des photos
      <input type="file" id="gallery_file_input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none">
    </label>
    <span class="adm-hint" style="display:block;margin-top:6px">
      Plusieurs fichiers acceptés. JPG, PNG, WebP — max <?= round(UPLOAD_MAX_SIZE_MEDIA / 1048576) ?> Mo par image.
    </span>
  </div>

  <!-- ── Vidéo YouTube ─────────────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Vidéo YouTube (optionnel)</p>
    <div class="adm-field">
      <label class="adm-label" for="f_video_url">URL YouTube</label>
      <input id="f_video_url" type="url" name="video_url" class="adm-input"
             placeholder="https://www.youtube.com/watch?v=..."
             value="<?= e($v_video_url) ?>">
      <span class="adm-hint">Coller l'URL complète de la vidéo YouTube. L'embed apparaît dans l'article après le corps du texte.</span>
    </div>
    <div id="yt_preview" style="margin-top:14px;display:none">
      <iframe id="yt_iframe" src="" frameborder="0" allowfullscreen loading="lazy"
              style="width:100%;aspect-ratio:16/9;border-radius:8px;display:block"></iframe>
    </div>
  </div>

  <!-- ── Publication ───────────────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Publication</p>
    <div class="adm-form-grid">
      <div class="adm-field">
        <label class="adm-label" for="f_status">Statut</label>
        <select id="f_status" name="status" class="adm-select">
          <option value="draft"     <?= $v_status === 'draft'     ? 'selected' : '' ?>>Brouillon</option>
          <option value="published" <?= $v_status === 'published' ? 'selected' : '' ?>>Publié</option>
        </select>
      </div>
      <div class="adm-field">
        <label class="adm-label" for="f_pub_at">Date de publication</label>
        <input id="f_pub_at" type="datetime-local" name="published_at" class="adm-input"
               value="<?= e($v_pub_at) ?>">
        <span class="adm-hint">Vide = date actuelle à la publication.</span>
      </div>
    </div>
  </div>

  <!-- ── Analyse SEO ──────────────────────────────────────── -->
  <div class="adm-card" id="seo-panel">
    <p class="adm-card-title" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <span>🔍 Analyse SEO en direct</span>
      <span id="seo-score-badge" style="font-size:.8rem;font-weight:900;padding:4px 14px;border-radius:20px;background:#e8e2db;color:#6b7f96">—</span>
    </p>
    <div id="seo-checklist" style="display:flex;flex-direction:column;gap:7px">
      <p style="font-size:.82rem;color:#6b7f96;font-style:italic">Chargement de l'analyse…</p>
    </div>
    <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f0ece7;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <button type="button" id="pagespeed-btn" onclick="runPageSpeed()"
              style="padding:7px 14px;border-radius:7px;border:1.5px solid #d0cbc5;background:#fff;cursor:pointer;font-size:.78rem;font-weight:700;color:#0c1e2e;transition:background .12s">
        ⚡ Tester PageSpeed
      </button>
      <a href="seo.php" style="font-size:.76rem;color:#6b7f96;font-weight:600">Vue d'ensemble SEO →</a>
    </div>
    <div id="pagespeed-result" style="margin-top:12px"></div>
  </div>

  <!-- ── Actions ───────────────────────────────────────────── -->
  <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;margin-top:4px;margin-bottom:32px">
    <a href="echos.php" class="btn-adm btn-adm-ghost">Annuler</a>
    <button type="submit" class="btn-adm btn-adm-primary">
      <?= $is_edit ? '✓ Sauvegarder' : '+ Créer l\'article' ?>
    </button>
  </div>

</form>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
