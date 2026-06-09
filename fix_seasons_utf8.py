#!/usr/bin/env python3
filepath = '/home/user/mika/zone85_v12/zone85_php/admin/seasons.php'

with open(filepath, 'rb') as f:
    data = f.read()

FFFD = b'\xef\xbf\xbd'
print("Initial FFFD count:", data.count(FFFD))

# All replacements — specific/longer patterns FIRST to avoid substring conflicts
replacements = [
    # Line 145 — two «/» guillemets + créée
    (b'Saison \xef\xbf\xbd {$title} \xef\xbf\xbd cr\xef\xbf\xbd\xef\xbf\xbde en brouillon.',
     'Saison « {$title} » créée en brouillon.'.encode('utf-8')),

    # Line 187 — activée + ont été fermées
    (b'activ\xef\xbf\xbde. Les autres saisons actives ont \xef\xbf\xbdt\xef\xbf\xbd ferm\xef\xbf\xbdes.',
     'activée. Les autres saisons actives ont été fermées.'.encode('utf-8')),

    # Line 173 — éventuelles + précédentes
    (b'\xef\xbf\xbdventuelles saisons actives pr\xef\xbf\xbdc\xef\xbf\xbddentes',
     'éventuelles saisons actives précédentes'.encode('utf-8')),

    # Line 219 — Déterminer + élevé
    (b'D\xef\xbf\xbdterminer le clan gagnant (score le plus \xef\xbf\xbdlev\xef\xbf\xbd sur la saison)',
     'Déterminer le clan gagnant (score le plus élevé sur la saison)'.encode('utf-8')),

    # Line 263 — Insérer + trophée
    (b'Ins\xef\xbf\xbdrer le troph\xef\xbf\xbde pour le clan gagnant',
     'Insérer le trophée pour le clan gagnant'.encode('utf-8')),

    # Line 289 — Créer + actualité
    (b"Cr\xef\xbf\xbder un fil d'actualit\xef\xbf\xbd (community_feed)",
     "Créer un fil d'actualité (community_feed)".encode('utf-8')),

    # Line 315 — clôturée (ô + é)
    (b'cl\xef\xbf\xbdtur\xef\xbf\xbde.',
     'clôturée.'.encode('utf-8')),

    # Line 953 — Clôturer définitivement ... irréversible
    (b'Cl\xef\xbf\xbdturer d\xef\xbf\xbdfinitivement cette saison ? Cette action est irr\xef\xbf\xbdversible.',
     'Clôturer définitivement cette saison ? Cette action est irréversible.'.encode('utf-8')),

    # Line 643 — Créer, activer et clôturer
    (b'Cr\xef\xbf\xbder, activer et cl\xef\xbf\xbdturer les saisons de jeu Zone85.',
     'Créer, activer et clôturer les saisons de jeu Zone85.'.encode('utf-8')),

    # Line 811 — Résumé en une phrase…
    (b'R\xef\xbf\xbdsum\xef\xbf\xbd en une phrase\xef\xbf\xbd',
     'Résumé en une phrase…'.encode('utf-8')),

    # Line 821 — présentation complet de la saison…
    (b'pr\xef\xbf\xbdsentation complet de la saison\xef\xbf\xbd',
     'présentation complet de la saison…'.encode('utf-8')),

    # Line 857 — Créez la première
    (b'Cr\xef\xbf\xbdez la premi\xef\xbf\xbdre',
     'Créez la première'.encode('utf-8')),

    # Line 711 — Généré automatiquement + éditable
    (b'G\xef\xbf\xbdn\xef\xbf\xbdr\xef\xbf\xbd automatiquement depuis le titre, \xef\xbf\xbdditable.',
     'Généré automatiquement depuis le titre, éditable.'.encode('utf-8')),

    # Line 773 — « Aucun badge »
    (b'\xef\xbf\xbd Aucun badge \xef\xbf\xbd',
     '« Aucun badge »'.encode('utf-8')),

    # Line 793 — « Aucune mission principale »
    (b'\xef\xbf\xbd Aucune mission principale \xef\xbf\xbd',
     '« Aucune mission principale »'.encode('utf-8')),

    # Line 1077 — « Aucune »
    (b'\xef\xbf\xbd Aucune \xef\xbf\xbd',
     '« Aucune »'.encode('utf-8')),

    # Lines 903/905 — em-dash placeholder in span
    (b'<span style="color:#aaa">\xef\xbf\xbd</span>',
     '<span style="color:#aaa">—</span>'.encode('utf-8')),

    # Line 533 — nav — patch
    (b'nav \xef\xbf\xbd patch',
     'nav — patch'.encode('utf-8')),

    # Line 661 — ⚠ warning icon
    (b"'ok' ? '' : ($flash['type'] === 'info' ? '' : '\xef\xbf\xbd')",
     "'ok' ? '' : ($flash['type'] === 'info' ? '' : '⚠')".encode('utf-8')),

    # --- Now single-FFFD patterns ---

    # Line 51 — annulée
    (b'annul\xef\xbf\xbde',
     'annulée'.encode('utf-8')),

    # Lines 151/609/679 — création
    (b'cr\xef\xbf\xbdation',
     'création'.encode('utf-8')),

    # Line 179 — demandée
    (b'demand\xef\xbf\xbde',
     'demandée'.encode('utf-8')),

    # Line 313 — trophée
    (b'troph\xef\xbf\xbde',
     'trophée'.encode('utf-8')),

    # Line 323 — clôture
    (b'cl\xef\xbf\xbdture',
     'clôture'.encode('utf-8')),

    # Lines 419/425 — mise à jour
    (b'mise \xef\xbf\xbd jour',
     'mise à jour'.encode('utf-8')),

    # Line 497 — éligibles
    (b'\xef\xbf\xbdligibles',
     'éligibles'.encode('utf-8')),

    # Lines 497/789/1073 — cachée
    (b'cach\xef\xbf\xbde',
     'cachée'.encode('utf-8')),

    # Lines 591/971 — d'édition
    (b"d'\xef\xbf\xbddition",
     "d'édition".encode('utf-8')),

    # Lines 649/831 — Créer
    (b'Cr\xef\xbf\xbder',
     'Créer'.encode('utf-8')),

    # Line 673 — données
    (b'donn\xef\xbf\xbdes',
     'données'.encode('utf-8')),

    # Lines 749/1029 — Date de début (capital D)
    (b'D\xef\xbf\xbdbut',
     'Début'.encode('utf-8')),

    # Lines 923/925 — Éditer
    (b'\xef\xbf\xbdditer',
     'Éditer'.encode('utf-8')),

    # Line 933 — fermées
    (b'ferm\xef\xbf\xbdes',
     'fermées'.encode('utf-8')),

    # Lines 951/961 — Clôturer
    (b'Cl\xef\xbf\xbdturer',
     'Clôturer'.encode('utf-8')),

    # Line 877 — Début (table header, lowercase context: <th>Début</th>)
    (b'd\xef\xbf\xbdbut',
     'début'.encode('utf-8')),
]

for old, new in replacements:
    count = data.count(old)
    if count > 0:
        data = data.replace(old, new)
        print("  Replaced", count, "x:", old[:50])
    else:
        print("  NOT FOUND:", old[:60])

remaining = data.count(FFFD)
print("\nRemaining FFFD after replacements:", remaining)

if remaining == 0:
    with open(filepath, 'wb') as f:
        f.write(data)
    print("File written successfully.")
else:
    lines = data.split(b'\n')
    for i, line in enumerate(lines, 1):
        if FFFD in line:
            print("  Still broken line", i, ":", repr(line[:120]))
    print("File NOT written — fix remaining issues first.")
