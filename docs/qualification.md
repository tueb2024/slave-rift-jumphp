# Qualification des trois modes — 2 novembre 2026

## Source et historique

Au départ de cette qualification, la branche distante n’existait pas et `main` était à `9ea6094c3879d9d06559e6cbcdded38ee00312fb`. Le connecteur a publié la baseline locale sans changer ses arbres :

| Commit local antérieur | Équivalent distant |
|---|---|
| b467842 | 8f703d041f54b889eb0724f2867cc8c308e43cb7 |
| 27c07e3 | 7dab5fca55760e1dfd3f41f286f4f7381c633a55 |

La qualification part donc de **7dab5fca55760e1dfd3f41f286f4f7381c633a55**. Le replay conserve aussi la carte explicite des anciennes fixtures de démo/tests (`procedural: false`). Les nouveaux commits sont enregistrés directement sur `release/playable-rc`, avec vérification du SHA distant et mise à jour conditionnelle sans force. Aucun merge vers main.

- `0f5b9a3` : contrats NORMAL/DAILY/HORDE, salle Horde, limites distinctes et benchmark CPU.
- `0784d25` : menus utilisables sans run actif, replay explicite, nouvelle map, navigation tactile/manette/clavier, garde contre les doubles taps.
- `908bc35` : rapports détaillés, profil simulé moins informé, proportions exactes des PV et feedback du boss.

## État fonctionnel

« OK » signifie ici contrôlé par du code exécuté et/ou des entrées navigateur automatisées ; cela ne remplace pas un playtest humain.

| Élément | État | Preuve / limite |
|---|---|---|
| MENU | OK | MenuFlow testé ; aucun Run au démarrage ; souris, clavier et taps navigateur. |
| NORMAL | OK | Parcours procédural, boss, trésor, victoire ; simulation multi-seeds. |
| REPLAY SAME MAP | OK | Action explicitement séparée ; seed identique vérifiée dans le navigateur. |
| NEW RANDOM MAP | OK | Nouvelle seed différente du run courant ; aucune persistance ajoutée. |
| DAILY 1 | OK | Date/niveau reproductibles ; parcours simulé jusqu’à COMPLETE. |
| DAILY 2 | OK | Idem, configuration plus intense. |
| DAILY 3 | OK | Idem. |
| DAILY 4 | OK | Idem ; terminable par le profil bon sur la date testée. |
| HORDE | OK | Salle unique, pas d’objectif final ; limite indépendante et stress jusqu’à 200. |
| MOBILE NAVIGATION | OK automatique | Vrais taps Chromium mobile/HiDPI sur les choix et écrans de fin ; appareil réel restant à tester. |
| BOSS → TREASURE → VICTORY | OK | Tirs rythmiques réels en PHPUnit ; fixtures visuelles et collecte jusqu’à won en WASM. |

## Ce que le simulateur démontre et ne démontre pas

Le premier rapport était trop favorable pour conclure à un bon équilibrage : il ne mesurait pas les PV aux transitions, et son ciblage voyait les ennemis derrière la caméra. Le nouveau rapport conserve les détails par seed et ajoute les checkpoints salle finale, boss, boss vaincu et trésor, avec PV, PV maximum, ratio de santé, niveau, temps et arme. Les distributions donnent minimum/moyenne/maximum ; les armes, vagues de décès et timeouts sont comptés séparément.

Les profils ne disposent plus d’une acquisition omnisciente à 360° : sans blessure récente, ils ne cherchent une cible que devant eux. Ils manquent 50/20/5 % des opportunités de collecte XP, réagissent avec retard et ont une petite erreur de trajectoire. Les valeurs des armes, dégâts et PV du jeu ne sont pas modifiées par le simulateur. Celui-ci connaît encore les prochaines portes et les positions des zones annoncées du boss : il ne représente pas l’incertitude d’un premier joueur.

### Parcours normal — 10 seeds par profil, limite 360 secondes

| Profil | Salle finale | Accès boss | Boss vaincu | Trésor / victoire | Décès | Timeout |
|---|---:|---:|---:|---:|---:|---:|
| Faible | 50 % | 50 % | 30 % | 30 % | 60 % | 10 % |
| Moyen | 100 % | 100 % | 100 % | 100 % | 0 % | 0 % |
| Bon | 100 % | 100 % | 100 % | 100 % | 0 % | 0 % |

| Profil | PV salle finale min/moy/max | PV entrée boss min/moy/max | PV après victoire min/moy/max |
|---|---|---|---|
| Faible | 70 / 94,2 / 113 | 70 / 91,6 / 120 | 10 / 39,3 / 88 |
| Moyen | 100 / 106 / 120 | 40 / 92 / 133 | 62 / 98 / 133 |
| Bon | 100 / 102 / 120 | 48 / 83,2 / 119 | 48 / 89,9 / 120 |

Les moyennes de transition ne portent que sur les runs ayant atteint cette transition. Les PV maximum changent avec Vitalité ; le JSON fournit aussi les ratios. Le profil faible meurt trois fois à la vague 1 et trois fois à la vague 2. Sa seed 8 termine sur un timeout de navigation, sans atteindre la salle finale. Le test de marche sans combat traverse cette seed : cela ne démontre pas une route physiquement bloquée.

**Réserve d’équilibrage :** aucun profil moyen de cet échantillon ne descend sous 30 % de ses PV. Sa réussite à 100 % ne valide donc pas la sensation de danger. La courbe normale est conservée ; durcir le jeu pour faire perdre ce bot risquerait de réintroduire le mur humain. Les tests A et C ci-dessous doivent trancher entre courbe trop permissive et pilote encore trop efficace.

### Vagues 6 → 7

| Paramètre NORMAL | Vague 6 | Vague 7 |
|---|---:|---:|
| Coefficient PV | 1,125 | 1,15 |
| Unité de PV arrondie | 27 | 28 |
| Runner / guardian / enforcer / brute | 27 / 54 / 81 / 162 | 28 / 56 / 84 / 168 |
| Composition | 42 / 34 / 18 / 6 % | 32 / 33 / 23 / 12 % |
| Budget | 22 | 24 |
| Lot d’apparition | 2 | 2 |
| Intervalle de lot | 1,16 s | 1,12 s |

Le passage de composition reste un palier (la part de brutes double) ; les tests ne le masquent pas. Il n’y a plus de compensation des PV selon la puissance du joueur ni deux mises à l’échelle successives. Run applique une seule unité de PV à l’apparition, puis le poids de classe 1/2/3/6. La correction d’arrondi fait passer les PV de vague 7 de 28/55/83/166 à 28/56/84/168 : elle rend les rapports exacts, sans chercher à durcir la partie. DifficultyDirector fournit les profils, bonus d’armes et estimations de diagnostic ; ses anciens hooks adaptatifs ne mutent plus les rangs ou ennemis.

Le scénario arène isole cette courbe de la navigation. À la vague 7 :

| Profil | Runs ayant atteint V7 | Niveau moyen | Arme | PV moyens |
|---|---:|---:|---|---:|
| Faible | 3/10 | 13,0 | Voir distribution JSON | 145,7 |
| Moyen | 10/10 | 15,8 | Double MP44 ou BAR | 139,8 |
| Bon | 10/10 | 16,9 | Double MP44 ou BAR | 135,8 |

Les niveaux d’armes restent 1/4/8/12/16/20/24. Un test mesure les vrais impacts d’une même séquence rythmique : le double MP44 inflige deux fois les dégâts du MP44 simple. Le rythme reste à 116 BPM +2 par niveau.

## Boss et visuel

Le boss est instancié dans Run, placé à partir de l’ancre centrale (les ailes ne l’écrasent plus), représenté avec l’asset lourd CC0 existant, et activé après le nettoyage de la salle. Les orbes sont visibles et reçoivent les tirs ; leur disparition ouvre six secondes de dégâts au cœur. Le HUD affiche les orbes restantes puis un compte à rebours de vulnérabilité ; le cadre devient vert. Les phases augmentent la fréquence et les dégâts des explosions annoncées au sol. La mort déclenche TREASURE_READY ; l’approche du coffre doré vérifie aussi la hauteur et déclenche COMPLETE/won.

Les captures réelles de la CI ont été examinées : menus, mort, boss, vulnérabilité et trésor sont représentés. Un texte temporaire passait devant les orbes ; il est désormais masqué pendant le combat car le HUD donne déjà la consigne. Aucun trou n’apparaît dans les vues de salle finale examinées ; cela ne vaut pas inspection exhaustive de toutes les seeds. Les 16 parcours du test de collision se traversent sans saut ni téléportation.

Les textes internes au jeu sont français ; la police du moteur retire les accents. La barre HTML externe du moteur affiche encore SOUND/FULLSCREEN : ces deux libellés appartiennent au shell JumPHP épinglé, pas aux chaînes du jeu. Aucun contournement JavaScript non documenté n’a été ajouté pour les modifier.

## Benchmark Horde

Le HUD mesure les intervalles réels entre updates avec une horloge flottante : le delta de physique du moteur est plafonné à 100 ms et masquerait autrement les gros blocages. Un test vérifie qu’un intervalle de 250 ms reste visible. Le plafond normal reste 48. Horde fait monter sa limite active de 24 à 200 ; cette valeur est une borne mesurée, pas une promesse de fluidité graphique. Le benchmark instancie une population fixe, désactive les tirs et utilise beaucoup de PV pour mesurer le CPU sans interrompre le test. Il ne sert pas à calculer un taux de victoire.

Résultats bruts et temps de la machine de test : `horde-stress-report.json`. Chaque population 50/100/150/200 reste stable pendant 300 pas, soit 5 secondes simulées à 60 Hz. Un test de non-régression couvre également ces quatre populations. La moyenne, le maximum et le p95 sont fournis, avec la mémoire PHP maximale ; aucune mesure GPU, VRAM ou FPS WebGL n’est inférée de ces nombres.

## Trois playtests humains à effectuer

### A — ordinateur, partie normale

Depuis le menu, choisir JOUER, avancer sans chercher un coin optimal, collecter une partie de l’XP et choisir librement les améliorations. Noter les PV/niveau/arme à l’arrivée en salle finale et au boss ; vérifier que l’objectif reste lisible. Détruire les orbes, profiter du cœur ouvert, éviter les zones rouges, prendre le coffre et vérifier VICTOIRE. Essayer REJOUER CETTE MAP puis NOUVELLE MAP ALÉATOIRE et MENU PRINCIPAL. Noter toute confusion, collision ou séquence sans danger.

### B — téléphone tactile, prioritaire

Tester paysage puis un changement d’orientation. Toucher COMMANDES, les quatre niveaux du défi, HORDE et JOUER ; revenir au menu entre les essais. En partie, utiliser joystick, SAUT, sélection tactile des améliorations et PAUSE/REPRENDRE. Se laisser mourir puis vérifier replay même map, nouvelle map et retour au menu sans rechargement. Après une victoire normale ou quotidienne, vérifier le choix de replay. Contrôler les boutons dans les bandes latérales, leur taille et l’absence de double activation. Le test automatisé reproduit ces taps en Chromium mobile HiDPI ; il ne teste pas un Android physique ni Safari iOS.

### C — partie médiocre

Manquer quelques cristaux, hésiter, subir des coups et choisir des améliorations non optimales. Noter la vague du premier vrai danger et du décès éventuel. Le joueur doit avancer assez loin sans victoire automatique ; relever les soins disponibles et les ratios de PV aux moments difficiles. Si la partie moyenne ne met jamais en danger, ajuster ensuite un paramètre à la fois, avec un nouveau rapport.

## Reproductibilité CI

Une exécution a échoué avant les tests parce que Composer essayait de cloner `php-wasm-network` via SSH, absent de l’image Docker. Le staging des workflows impose désormais HTTPS pour cette dépendance publique. Les tests du jeu ne sont pas désactivés et aucune clé SSH n’est ajoutée.

## Verdict

**RC prête à qualifier**, sous réserve du dernier résultat CI et du playtest humain. Les contrôles automatiques ne constituent pas une validation de plaisir ou de difficulté. Aucun leaderboard, backend, stockage persistant de seed ou merge vers main n’a été ajouté.
