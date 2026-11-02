# XP et campagne expérimentale — étude du 2 novembre 2026

Référence auditée : `8bc08f079d5a9d58d22f329864b230efad5aa06b`, branche `release/playable-rc`.

## Comparaison avant changement

Le seuil du niveau L est `6 + floor((L−1) × 0,7)`. Les armes actuelles arrivent aux niveaux 4/8/12/16/20/24, soit 19/54/100/157/225/305 XP cumulés. Aucun multiplicateur XP par vague. Un soin de 18 PV remplace le drop XP tous les 9 kills : perte attendue de 11,1 % d'XP, sans modifier les soins.

### Calcul avec les vraies compositions et budgets

| Vagues | runner / guardian / enforcer / brute | XP moyenne actuelle | XP 1/2/3/6 | XP 2/3/4/6 |
|---|---|---:|---:|---:|
| 1 | 68 / 32 / 0 / 0 % | 2,00 | 1,32 | 2,32 |
| 2–3 | 54 / 34 / 12 / 0 % | 2,00 | 1,58 | 2,58 |
| 4–6 | 42 / 34 / 18 / 6 % | 2,18 | 2,00 | 2,94 |
| 7–10 | 32 / 33 / 23 / 12 % | 2,36 | 2,39 | 3,27 |
| ≥11 | 22 / 30 / 28 / 20 % | 2,60 | 2,86 | 3,66 |

Les budgets normaux sont `min(34,10+2×vague)`. Le fichier [xp-math.json](reports/xp-math.json) donne chaque vague, les soins comptés exactement à leurs positions, l'XP totale, les niveaux à 100/80/60 % de collecte et la vague interpolée des six déblocages.

| Modèle | Niveau V4, collecte 80 % | Niveau V7, collecte 80 % | Déblocages : double Colt / MP44 / double MP44 / BAR / Gatling / double Gatling, vague à 80 % |
|---|---:|---:|---|
| actuel | 11 | 18 | 1,07 / 2,70 / 4,36 / 6,10 / 7,71 / 9,42 |
| 1/2/3/6 | 9 | 16 | 1,45 / 3,32 / 5,00 / 6,66 / 8,18 / 9,82 |
| 2/3/4/6 | 13 | 21 | 0,93 / 2,23 / 3,63 / 5,02 / 6,44 / 7,78 |

Ce sont des niveaux calculés depuis l'espérance d'XP, pas une moyenne mesurée de joueurs. Ils supposent tous les ennemis des vagues éliminés. La salle finale peut être atteinte avant V4 : l'extrapolation « boss = vague 7 » serait fausse.

### Mesure du parcours court, 10 seeds × 3 profils × 3 modèles

Politique imparfaite identique, ennemis inchangés, anciens cooldowns indépendants pour isoler l'axe XP. Les niveaux d'arrivée sont conditionnels à l'accès au checkpoint. Les armes correspondent aux seuils existants.

| Modèle | Niveau salle finale moyen | Niveau boss moyen | Arme boss typique | XP collectée moyenne (profil moyen) | Victoire faible / moyen / bon |
|---|---:|---:|---|---:|---|
| actuel | 2,3 | 4,2 | double Colt | 28,2 | 30 / 100 / 100 % |
| 1/2/3/6 | 1,7 | 3,1 | Colt | 18,6 | 10 / 100 / 100 % |
| 2/3/4/6 | 2,5 | 5,2 | double Colt | 35,1 | 60 / 100 / 100 % |

Le modèle **2/3/4/6** est le candidat le plus cohérent : il préserve le début (runner 2 XP), récompense mieux guardian/enforcer et suit naturellement l'évolution de composition. Le modèle strict 1/2/3/6 pénalise trop l'ouverture. La résistance ne mesure pas toute la difficulté de tuer, donc une identité stricte HP/XP n'est pas nécessaire.

Une collecte de 80 % multiplie l'XP par 0,8 après la substitution des soins ; 60 % par 0,6. Les pertes se cumulent : à 80 %, on récupère environ 71,1 % de l'XP brute avant soins ; à 60 %, 53,3 %. Les armes n'affectent ni le nombre ni la valeur des soins. Augmenter l'XP peut augmenter la survie via l'arsenal et les upgrades : cela ne démontre pas que les paramètres ennemis ont été abaissés.

## Campagne : prototype de simulation

La campagne ne remplace pas le mode NORMAL. Elle doit être qualifiée avant d'être proposée comme mode définitif. L'API `CampaignRun` expose `level_complete`, puis `advance()` ; seule la récupération du quatrième trésor donne la victoire de campagne. Le prototype n'ajoute donc pas encore un écran jouable « niveau suivant » au menu.

Le graphe sature à 32 salles principales (`2×depth`, borné 12..32). Un depth 6/12/24/48 ne doublerait pas la taille. Le prototype utilise depths 6/9/12/16, puis adapte les dimensions horizontales pour doubler **la somme des emprises des salles**, murs inclus, à chaque étage. Ce n'est ni la profondeur du graphe ni la surface marchable exacte. Hauteurs inchangées, connecteurs/solides/spawns/ancres adaptés ensemble. Rapports d'emprise 1/2/4/8, pas de graphe à 96 salles.

La sauvegarde entre niveaux conserve XP résiduelle, niveau, upgrades, secondaires, PV maximum, score, kills et cooldowns. Les effets transitoires de tir et les ennemis ne traversent pas les niveaux. Seed d'étage : `(seed−1+7919×(étage−1)) modulo 65520 + 1`.

Le soin proposé rend **35 % des PV manquants** : `PV' = PV + round(0,35×(max−PV))`. Pour 20/100 : conservation 20, partiel 48, total 100. Sans aucun autre événement, trois transitions transformeraient 20 PV en environ 78, contre 20 ou 100. Cette récupération conserve une dette de dégâts ; elle ne remplace pas la mesure de survie en combat.

## Décision retenue

Le barème **runner 2 / guardian 3 / enforcer 4 / brute 6 XP** devient le défaut. Les coûts de niveau et les paliers d'armes du jeu court restent **4/8/12/16/20/24**. Aucun affaiblissement des ennemis normaux. La variante campagne utilise explicitement **3/5/8/11/14/17**, soit **12/27/54/87/127/173 XP** cumulés. Cette variante ne change pas les coûts de niveau : elle rapproche seulement les déblocages d'armes.

### XP attendue par vague

Valeurs après remplacement par les soins, avant collecte imparfaite. Les niveaux ci-dessous appliquent ensuite 80 % de collecte. Les compositions se trouvent au début du rapport ; les trois taux de collecte sont détaillés dans le JSON.

| Vague | Ennemis / soins | XP actuel / strict / accessible | Niveau à 80 %, actuel / strict / accessible |
|---|---|---|---|
| 1 | 12 / 1 | 22.00 / 14.52 / 25.52 | 3 / 2 / 4 |
| 2 | 14 / 1 | 26.00 / 20.54 / 33.54 | 6 / 5 / 7 |
| 3 | 16 / 2 | 28.00 / 22.12 / 36.12 | 8 / 7 / 10 |
| 4 | 18 / 2 | 34.88 / 32.00 / 47.04 | 11 / 9 / 13 |
| 5 | 20 / 2 | 39.24 / 36.00 / 52.92 | 13 / 12 / 15 |
| 6 | 22 / 3 | 41.42 / 38.00 / 55.86 | 15 / 14 / 18 |
| 7 | 24 / 3 | 49.56 / 50.19 / 68.67 | 18 / 16 / 21 |
| 8 | 26 / 2 | 56.64 / 57.36 / 78.48 | 20 / 19 / 24 |
| 9 | 28 / 4 | 56.64 / 57.36 / 78.48 | 22 / 21 / 27 |
| 10 | 30 / 3 | 63.72 / 64.53 / 88.29 | 25 / 24 / 30 |
| 11 | 32 / 3 | 75.40 / 82.94 / 106.14 | 27 / 27 / 33 |
| 12 | 34 / 4 | 78.00 / 85.80 / 109.80 | 30 / 30 / 36 |

## Campagne mesurée : 80 runs complets, seeds 1..10

Deux variantes de paliers, chacune 10 seeds × trois profils, puis 10 profils moyens sans soin de transition et 10 avec soin total. Toutes utilisent 2/3/4/6 XP et le scheduler des secondaires. Les anciennes comparaisons XP courtes utilisaient les cooldowns précédents : ne pas attribuer au seul XP un écart entre ces deux expériences. Chaque simulation peut jouer jusqu'à 900 secondes par étage. Aucun timeout dans ces 80 campagnes. La politique choisit des upgrades déterministes, collecte imparfaitement et se déplace ; elle n'altère jamais les PV ni les dégâts.

| Paliers | Profil | Accès L2 | Accès L3 | Accès L4 | Victoire | PV fin des gagnants, % du maximum |
|---|---|---:|---:|---:|---:|---:|
| 4,8,12,16,20,24 | faible | 60 % | 30 % | 20 % | 20 % | 36.8 % |
| 4,8,12,16,20,24 | moyen | 100 % | 100 % | 100 % | 90 % | 70.1 % |
| 4,8,12,16,20,24 | bon | 100 % | 90 % | 90 % | 90 % | 87.6 % |
| 3,5,8,11,14,17 | faible | 70 % | 70 % | 60 % | 50 % | 69.6 % |
| 3,5,8,11,14,17 | moyen | 100 % | 100 % | 100 % | 100 % | 70.0 % |
| 3,5,8,11,14,17 | bon | 100 % | 100 % | 100 % | 100 % | 89.9 % |

### Progression avec les paliers rapprochés

Moyennes conditionnelles à l'accès à l'étage, décès compris. Les armes indiquées sont les modes au **début du boss**, pas celles interpolées depuis le niveau moyen. Une moyenne fractionnaire n'est pas une arme réelle.

| Profil | Étage | Niveau départ → fin | Arme typique au boss | Boss atteints / vaincus | PV départ → fin | Durée de l'étage | Kills |
|---|---|---|---|---|---|---|---:|
| faible | 1 | 1.0 → 7.4 | MP44 | 7 / 7 | 100.0 → 55.3 | 138.5 s | 30.1 |
| faible | 2 | 8.3 → 14.3 | GATLING | 7 / 7 | 93.4 → 69.0 | 275.3 s | 42.6 |
| faible | 3 | 14.3 → 19.0 | DOUBLE GATLING | 6 / 6 | 90.9 → 102.1 | 454.9 s | 49.1 |
| faible | 4 | 19.5 → 23.8 | DOUBLE GATLING | 5 / 5 | 129.8 → 102.2 | 603.8 s | 47.5 |
| moyen | 1 | 1.0 → 5.4 | MP44 | 10 / 10 | 100.0 → 99.3 | 75.2 s | 17.5 |
| moyen | 2 | 5.4 → 9.1 | DOUBLE MP44 | 10 / 10 | 103.7 → 107.3 | 113.2 s | 21.6 |
| moyen | 3 | 9.1 → 12.0 | BAR | 10 / 10 | 113.0 → 109.7 | 173.4 s | 25.4 |
| moyen | 4 | 12.0 → 14.5 | GATLING | 10 / 10 | 116.8 → 103.6 | 266.5 s | 31.2 |
| bon | 1 | 1.0 → 5.4 | MP44 | 10 / 10 | 100.0 → 95.1 | 59.7 s | 16.0 |
| bon | 2 | 5.4 → 9.3 | DOUBLE MP44 | 10 / 10 | 100.4 → 104.5 | 87.1 s | 19.6 |
| bon | 3 | 9.3 → 13.7 | BAR | 10 / 10 | 110.0 → 115.0 | 131.7 s | 24.2 |
| bon | 4 | 13.7 → 17.3 | DOUBLE GATLING | 10 / 10 | 120.8 → 129.6 | 200.7 s | 28.9 |

Le [CSV des 80 campagnes](reports/campaign-runs.csv) contient chaque étage de chaque seed : niveau, XP totale, arme et secondaires au départ/à la fin, PV/max, durée, kills, boss atteint/vaincu, trésor, seed et géométrie. Le [JSON de synthèse](reports/campaign-summary.json) fournit les distributions d'armes aux boss et les instants de chaque premier déblocage. Les [90 parcours courts](reports/xp-route.csv) permettent de contrôler la comparaison initiale.

### Gatling et double Gatling

| Profil | Gatling : runs / étage d'apparition | Double Gatling : runs / étage d'apparition |
|---|---|---|
| faible | 7/10 ; L2: 6, L4: 1 | 5/10 ; L3: 5 |
| moyen | 9/10 ; L4: 9 | 0/10 ;  |
| bon | 10/10 ; L3: 6, L4: 4 | 7/10 ; L4: 7 |

Le profil moyen finit principalement à la Gatling ; il n'atteint pas la double Gatling dans ces dix seeds. Le bon profil l'obtient 7/10 fois, toujours au quatrième étage. Les faibles survivants passent plus de temps à combattre/ramasser et arrivent parfois très équipés dès L3 : ce n'est pas une récompense adaptative. Leur risque de mort reste supérieur. Il ne serait pas cohérent de promettre la même arme au même étage à tous les styles de déplacement.

La campagne fait découvrir significativement plus d'armes que la courte partie actuelle. Avec les paliers historiques, presque aucun profil rapide n'exploite Gatling/double Gatling. Les paliers rapprochés sont donc une meilleure **hypothèse de campagne**, pas un changement global imposé au jeu court.

### Soin entre étages, 10 seeds de profil moyen

| Soin des PV manquants | Victoire | PV fin des gagnants |
|---|---:|---:|
| 0 % | 100 % | 63.3 % du maximum |
| 35 % | 100 % | 70.0 % du maximum |
| 100 % | 100 % | 70.5 % du maximum |

**35 % des PV manquants** reste le choix provisoire. Le retirer entièrement n'abaisse ici les PV de fin que de 7 points : les soins en combat, la vitalité et la politique d'esquive dominent. Une restauration totale apporte peu au taux de victoire et gomme davantage la dette de dégâts. Cette comparaison ne suffit pas à qualifier la tension humaine.

## Difficulté : axe distinct, encore à qualifier

| Étage | Depth / salles principales | Intensité HP et budgets | Décalage composition | Dégâts | Cadence de spawn | Vague finale | PV boss | Cadence boss |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| 1 | 6 / 12 | ×1,00 | +0 vagues | ×1,00 | ×1,00 | 12 | 1200 | ×1,00 |
| 2 | 9 / 18 | ×1,12 | +2 | ×1,10 | ×1,08 | 15 | 1800 | ×1,12 |
| 3 | 12 / 24 | ×1,25 | +4 | ×1,20 | ×1,16 | 18 | 2700 | ×1,24 |
| 4 | 16 / 32 | ×1,40 | +6 | ×1,30 | ×1,24 | 22 | 4000 | ×1,36 |

Ces coefficients s'ajoutent explicitement à la courbe de vague existante, pas à un indice adaptatif de puissance. Les poids HP restent 1/2/3/6. Les dégâts et le nombre de mobs évoluent séparément des HP. Les trois phases et l'ouverture du cœur du boss sont conservées ; pression/cadence et dégâts progressent. Les gains d'armes ne sont pas annulés par des HP ×2/×4/×8.

**La campagne n'est pas promue en mode définitif.** 100 % des moyens gagnent avec en moyenne 70 % de leurs PV maximum. Cela ne satisfait pas encore l'objectif de tension finale, malgré les morts des profils faibles. Changer uniquement les soins de transition ne résout pas ce constat. Il faut un playtest humain de la pression des boss tardifs, de la fréquence de vitalité/soins et du temps de navigation, avant de renforcer un axe précis. Ne pas augmenter aveuglément les HP pour compenser la nouvelle progression.

Les niveaux agrandis ne sont pas encore qualifiés visuellement : la simulation valide une traversée, pas les matériaux, les angles de vue ou le coût du rendu de L4. La transition « niveau suivant » est testée via l'API, sans écran de récapitulatif jouable. Le mode NORMAL, quotidien et Horde gardent leurs menus existants.

## Secondaires réellement implémentés

Trois armes : lance-flammes, mini-roquettes et impulsion. Aucun sniper/bazooka supplémentaire fictif. Les six PNG originaux (normal/assombri, 32×16) sont CC0, sources dans `tools/build_secondary_icons.py`, provenance dans `assets/ASSETS.md`. Aucun asset propriétaire ajouté.

Le rail en haut à droite n'affiche que les armes acquises. Sprite clair = prête, assombri + jauge = recharge ; cadre orange = flamme active, flash bref = départ. La principale conserve son rendu en bas. Les cooldowns réels dépendent des rangs/haste ; les valeurs sont lues depuis Run, pas recalculées dans le HUD.

Arbitrage FIFO déterministe : près d'un ennemi (2,5 unités), impulsion → flammes → roquettes ; sinon roquettes → flammes → impulsion. Cette règle ordonne les nouvelles arrivées ; une arme déjà en attente garde sa place. Une arme sans cible reste prête et n'empêche pas une autre arme éligible de tirer. Un seul départ par intervalle minimal `30/BPM`, environ 0,259 s à 116 BPM. Le cooldown commence au tir, jamais au moment de l'attente. Un balayage de flammes déjà actif continue ses ticks pendant un départ ultérieur ; le scheduler sérialise les **activations**, pas la durée de tous les effets.

Les secondaires peuvent désormais cibler les orbes/le cœur suivant leur portée et cône. Le ciblage primaire et son renderer restent conservés. Les tests vérifient file simultanée, ordre, attente sans perte de cooldown, espacement réel dans Run et sprites acquis. Le navigateur capture aussi cet arsenal en desktop/mobile HiDPI.

## Reproduction

```sh
php tools/xp-analysis.php > xp-math.json
php tools/xp-compare.php 10 > xp-route-comparison.json
php tools/campaign-sim.php 10 2-3-4-6 .35 legacy baseline.jsonl > baseline.json
php tools/campaign-sim.php 10 2-3-4-6 .35 compact candidate.jsonl > candidate.json
python3 tools/campaign-report.py baseline.json candidate.json --csv campaign-runs.csv > campaign-summary.json
# Comparaisons de soins : même commande avec 0 puis 1 à la place de .35.
php tools/simulation-test.php
phpunit -c phpunit.pure.xml
php tools/sync-package.php --check
```

Le dernier argument JSONL est facultatif, ajouté en append : utiliser un fichier neuf pour éviter les doublons lors d'une relance. Les simulations sont reproductibles pour un code donné. Les rapports versionnés portent sur le moteur et le scheduler de cette passe ; le modèle et les paliers y sont explicites. Le changement du barème par défaut n'altère pas ces expériences qui le passent explicitement.

## Validation technique de la passe

- PHP : syntaxe, tests PHPUnit purs, 85 vérifications de simulation, parité des 116 fichiers du package.
- Série d'étude : 90 parcours courts, 30 campagnes aux paliers historiques, 30 aux paliers rapprochés, 20 comparaisons de soins ; sources et valeurs détaillées ci-dessus.
- Le HUD a passé les vrais tests WASM desktop et mobile HiDPI au commit `0e262fc` (captures `desktop-secondary-arsenal.png` / `mobile-secondary-arsenal.png`). Les fixtures d'arsenal isolent le rendu ; elles ne prouvent pas un run humain.
- Un échec natif initial est survenu avant les tests : authentification Composer GitHub et ownership du checkout dans Docker. Le workflow supprime les credentials persistés par checkout, transmet l'authentification GitHub éphémère à Composer et déclare uniquement `/workspace` comme répertoire Git sûr dans les conteneurs d'installation. Aucun secret ajouté au dépôt.
- La CI produit désormais un artifact de 30 campagnes sur PHP 8.5. Les builds WASM obsolètes d'une même branche sont remplacés par le plus récent.

À jouer humainement : lisibilité des trois petits sprites et flashes pendant une vraie mêlée, cadence des secondaires en montée de BPM, différence double MP44/BAR/Gatling, pression et esquive des boss L3/L4, soins/vitalité, visibilité et temps de traversée des cartes agrandies. Les taux de bots ne sont pas des probabilités de victoire humaines.
