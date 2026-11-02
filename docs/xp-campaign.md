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
