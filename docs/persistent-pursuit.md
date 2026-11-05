# Poursuite persistante — validation du 5 novembre 2026

Ces mesures précèdent le nouvel équilibrage : voir [la version de test progressive](progressive-difficulty.md) pour les réglages et résultats actuels.

Base distante : `81daa940fb8cac4620d0d5c06e9c69add0cef209`, branche `feature/808-sampled-score`. Aucun changement de statistiques de dégâts, PV, XP, budgets, plafonds ou progression des armes. Les quatre maps, boss et pouvoir exclusif sont conservés.

## Audit et correction

- L'ancien déplacement utilisait seulement cinq angles d'évitement local, sans navigation entre salles. `Navigation` reconstruit les liens entre connecteurs des salles déjà assemblées, choisit la prochaine porte par BFS mis en cache et utilise un A* local si nécessaire. Le même système traite les maps historiques sans métadonnées via les collisions.
- L'acquisition est mémorisée dès une apparition visible, avant le délai d'activation du Défi du jour. La perte de visibilité ne réinitialise pas la poursuite. Les chemins rejoignent la position courante du joueur, avec recalculs espacés de 0,65 à 1,07 s lorsque la cible se déplace ou le chemin s'épuise.
- Un enforcer ne garde sa distance que si la cible est visible. Les contrôles de géométrie aux attaques et aux impacts restent actifs ; aucun tir à travers les murs.
- Le franchissement des ennemis autorisait seulement 0,23 unité alors que les marches procédurales montent de 0,45. Il utilise désormais la limite de 0,58 du joueur et refuse les chutes supérieures à 0,8. Le terrain et le volume physique sont vérifiés à chaque mouvement.
- L'espacement additionnel de 0,42/0,55 entre ennemis créait des bouchons artificiels. Les rayons physiques sont conservés, avec une marge de 0,04. Les ennemis peuvent reculer lorsqu'un encadrement ou un autre mob bloque les angles avant.
- Toutes les deux secondes, l'absence de déplacement ou de réduction de la distance restante provoque un recalcul. Aucun déplacement de récupération ne téléporte un ennemi.
- Le nettoyage des ennemis extérieurs à l'entrée de la salle finale est supprimé. Les survivants restent requis pour achever la vague et déclencher le boss.
- Le bot de simulation visait les objectifs sans détour, pouvait dépasser ses petits waypoints entre deux réactions et ne cherchait pas les derniers tireurs derrière sa caméra. Ses déplacements suivent désormais la navigation réelle ; les points franchis entre deux décisions sont consommés ; une rotation de recherche révèle visuellement les dernières menaces. Aucune cible cachée n'est révélée au bot.

## Coût et limites

Un index spatial des brushes remplace les balayages complets pour le support et les collisions. Au plus deux recherches ennemies démarrent par frame. Les recherches A* sont suspendues et reprises : budget global de 96 expansions par frame, plus au plus deux expansions d'initialisation ; plafond de 2 048 par recherche. Les tâches des ennemis éliminés sont annulées. Les requêtes de navigation du bot sont synchrones et bornées séparément ; la télémétrie les inclut et peut donc compter trois démarrages dans une frame.

Le joueur peut tenir dans un espace trop étroit pour un guardian ou une brute : une destination proche, accessible à portée de mêlée, évite de chercher indéfiniment sa coordonnée exacte. Une vraie séparation géométrique, par exemple un trou infranchissable, ne donne jamais lieu à un chemin inventé.

## Résultats

Les seeds quotidiennes sont **33771 / 4262 / 40273 / 10764**. La limite de 1 800 s s'applique à la campagne entière, sans réinitialisation à chaque map.

### Simulation normale, dégâts actifs

| Profil | Avant | Après |
|---|---|---|
| Faible | Limite de 30 min, map 1, 4 survivants | Mort au combat à 7,9 s, map 1 |
| Moyen | Mort à 10,1 s, map 1 | Mort au combat à 128,6 s, map 1 |
| Bon | Limite de 30 min, map 1, 17 survivants | Mort au combat à 101,6 s, map 1 |

Aucun timeout ni échec de recherche dans ces trois nouvelles parties. **Aucune victoire : la difficulté n'est pas validée.** La disparition des anciens softlocks ne prouve pas l'équilibrage. Ces politiques imparfaites ne prédisent pas un taux de victoire humain. Les dernières variations du bot et de l'évitement changent les trajectoires et donc les résultats : seules les mesures versionnées ci-dessous font référence.

### Contrôle distinct de navigation et progression

Une seconde simulation utilise les mêmes armes, upgrades, collisions et inputs, en neutralisant les dégâts par l'invincibilité de test. Elle n'est pas une mesure de difficulté.

- Moyen : quatre maps, quatre boss et quatre trésors, **22 min 05 s**, 1 064 éliminations.
- Bon : quatre maps, quatre boss et quatre trésors, **26 min 16 s**, 1 064 éliminations.
- Faible : limite de 30 min sur la quatrième map, **1 000 éliminations, zéro ennemi vivant et zéro ennemi restant à faire apparaître dans la vague courante**. La salle finale reste plus loin sur sa route ; ce timeout n'est pas causé par un survivant perdu. Son déplacement continue au relevé final.

Un prolongement séparé du contrôle faible, repris après sa limite de 30 minutes, termine la campagne à **32 min 24 s**, avec 1 064 éliminations et les quatre trésors. Cela confirme la lenteur du parcours plutôt qu’un verrou de progression ; cette reprise ne remplace pas le résultat officiel limité à 30 minutes.

La cible de **12 à 18 minutes n'est pas validée**, même dans le contrôle sans dégâts. Aucun ajustement d'équilibrage n'a été fait dans cette passe.

### Charge

Groupes de 25 et 50 poursuivants, 90 secondes à travers les vraies salles, portes et escaliers de la seed 33771 : tous franchissent les premières salles et l'entrée de l'escalier ; aucun échec de recherche ni disparition. Avec 50 mobs : moyenne **1,06 ms**, p95 **1,95 ms**, maximum **7,40 ms** par step PHP. La population reste constante, sans armes et avec une cible à PV élevés dans ce seul fixture de charge.

Horde ouverte à 200 ennemis : moyenne **2,53 ms**, p95 **4,74 ms**. Il s'agit de CPU PHP, sans mesure de rendu, de GPU ou de téléphone réel. Les plafonds de production ne sont pas modifiés.

## Vérifications et reproduction

- 96 tests PHPUnit, 137 301 assertions ; dont douze tests dédiés aux pertes de vue, murs, portes, foules, escaliers, budgets, récupération, quatre maps, bot et transition finale.
- 94 contrôles de `simulation-test.php`.
- Contrats du sampler conservés : 53 PCM, canaux bornés, fermeture audio.
- Syntaxe PHP et parité des sources/package vérifiées.
- L'ancien test exigeant une victoire de chaque bot moyen est adapté pour distinguer mort au combat et timeout ; la traversabilité et les transitions sont contrôlées séparément par les nouveaux tests et le contrôle sans dégâts.

```sh
php tools/sync-package.php --check
phpunit -c phpunit.pure.xml
php tools/simulation-test.php
php tools/daily-pursuit-report.php 2026-11-05 > daily-pursuit.json
php tools/daily-pursuit-report.php 2026-11-05 /tmp/progress.json --navigation-only > navigation-only.json
php tools/pursuit-stress.php > pursuit-stress.json
php tools/horde-stress.php > horde.json
```

La CI ajoute les trois rapports de poursuite aux artifacts `pursuit-php-8.2` et `pursuit-php-8.5`. Les résultats locaux sont enregistrés dans [docs/pursuit](pursuit/) : baseline, parties normales, contrôle sans dégâts et performances.
