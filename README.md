# Slave Rift — candidat 0.4.0-rc.1

Trois modes partagent le même FPS en PHP/JumPHP : NORMAL, DÉFI DU JOUR et HORDE. Le mode normal propose : tirs automatiques rythmiques, expérience, améliorations, niveau procédural, salle finale, boss mural, trésor et victoire.

Le dépôt d’auteur est à la racine. Le package importé par JumPHP est **`games/slave-rift/`**, synchronisé par `php tools/sync-package.php`. Importer `https://github.com/tueb2024/slave-rift-jumphp` avec la branche contenant les changements souhaités. La préparation RC est sur `release/playable-rc` ; aucun changement de cette branche n’est publié automatiquement avant son push.

## Jouer

- Accueil : **JOUER**, **DÉFI DU JOUR**, **HORDE**, **COMMANDES**. Cliquer/toucher ou sélectionner avec haut/bas puis Entrée, Espace ou A. Aucune partie ne tourne derrière le menu.
- Avancer/reculer : Z/W, S ou haut/bas. Déplacement latéral : Q/A, D. Tourner : gauche/droite.
- Saut : Espace ou B de la manette. Les armes tirent automatiquement sur les cibles visibles devant vous.
- Améliorations : toucher une ligne, 1/2/3, ou haut/bas puis Entrée/A.
- Pause : P, Échap, Select ou bouton **PAUSE** à l’écran. Reprendre par le bouton, Entrée/A ou P. Recommencer par le bouton ou R/X depuis la pause.
- Mort/victoire en NORMAL : **REJOUER CETTE MAP** conserve explicitement la seed ; **NOUVELLE MAP ALÉATOIRE** la change ; **MENU PRINCIPAL** libère la partie. Entrée/Espace/A valide le choix surligné. Aucun rechargement de page. Le restart depuis la pause normale choisit une nouvelle seed.
- Mobile : joystick pour avancer/tourner, SAUT, PAUSE, OK et boutons directement dans le jeu. Le paysage est conseillé. Les taps fonctionnent aussi dans les améliorations et les écrans de fin.

La flèche indique la prochaine porte du parcours. Avancez, ramassez les cristaux d’XP et les soins, puis éliminez la vague de la salle finale. Détruisez les trois orbes du boss ; le cadre vert et le message du cœur annoncent sa vulnérabilité pendant six secondes. Évitez les zones rouges annoncées au sol. Après sa mort, approchez du coffre doré pour gagner.

## Progression

| Niveau | Arme principale |
|---|---|
| 1 | Colt .45 |
| 4 | Double Colt |
| 8 | MP44 |
| 12 | Double MP44 |
| 16 | BAR |
| 20 | Gatling |
| 24 | Double Gatling |

Le rythme reste à 116 BPM au départ, puis +2 BPM par niveau. Le double MP44 double les dégâts d’une même séquence de rafale. Les améliorations de puissance et les bonus d’arme ne font plus augmenter les PV ennemis.

PV de base : runner 24, guardian 48, enforcer 72, brute 144. Un seul coefficient de progression est appliqué à l’apparition : `1 + min(20, vague - 1) × 0,025`. Une unité commune de PV est arrondie, puis multipliée par le poids 1/2/3/6 ; les proportions restent exactes. Les vagues attendent leur élimination complète, avec quatre secondes de répit et dix PV rendus. Leur budget est plafonné à 34 ennemis ; les apparitions restent bornées à deux par lot et 48 ennemis actifs. La vitalité est plafonnée à 180 PV ; elle fournit ensuite des soins.

Le coût XP commence à 6 et augmente de `floor((niveau - 1) × 0,7)` ; la portée de collecte initiale est 3,2 unités. Les noms internes des améliorations restent stables ; les textes affichés sont français. La police bitmap JumPHP transforme les accents en lettres simples à l’affichage.


## Défi du jour et Horde

Les quatre défis utilisent le même calendrier **Europe/Paris** pour tous les joueurs et quatre seeds déterministes. Rejouer un défi garde sa configuration, même après minuit ; choisir un défi depuis le menu prend la date courante. Aucun compte ni stockage de seed dans le navigateur. `GameMode::daily(date, tier, serverSeed, dailyId)` accepte déjà un futur contrat serveur, sans backend dans cette version.

| Défi | Profondeur | Intensité PV/budget | Vague finale | PV du boss |
|---|---:|---:|---:|---:|
| 1 — accessible | 5 | 0,85 | 10 | 900 |
| 2 — normal | 7 | 1,00 | 12 | 1 200 |
| 3 — difficile | 9 | 1,15 | 16 | 1 500 |
| 4 — brutal | 11 | 1,30 | 20 | 1 800 |

HORDE utilise une salle fermée de 40 × 40 unités, sans boss ni trésor. Le budget augmente de six ennemis par vague ; l’intervalle d’apparition descend progressivement jusqu’à 0,12 s. Sa limite active distincte monte de 24 à **200 ennemis**, sans changer la limite de 48 du mode normal. Les PV continuent à croître linéairement. Cette borne est volontaire : les limites graphiques ne sont pas encore mesurées sur un vrai téléphone.

Avec le paramètre de scène `debug: true`, le HUD Horde affiche population, total généré, éliminations, objets, moyenne et maximum des 120 derniers temps de frame, FPS et mémoire PHP. Cette mémoire n’inclut pas la VRAM. Mesure CPU sans renderer :

```sh
php tools/horde-stress.php
```

Le rapport brut se trouve dans [docs/horde-stress-report.json](docs/horde-stress-report.json). Le protocole de playtest et les réserves d’équilibrage sont dans [docs/qualification.md](docs/qualification.md).

## Vérifier

Les tests de simulation n’ont besoin ni de JumPHP ni de Composer :

```sh
php tools/simulation-test.php
php tools/sync-package.php --check
php tools/balance-sim.php 10 360 both > balance-report.json
```

Le simulateur produit les résultats par seed et profil, les vagues de décès, les PV, le niveau/l’arme à la vague 7, l’accès à la salle finale, au boss et à la victoire. `route` teste le véritable niveau procédural ; `survival` isole la courbe dans une arène ouverte. Les profils font des erreurs de visée, hésitent, réagissent avec retard et collectent différemment. Ils ne changent jamais les PV, l’XP ou les dégâts du jeu. Leurs taux de succès ne prédisent pas ceux de joueurs humains.

Avec PHPUnit 11 (PHP 8.3+, ou PHPUnit 10 sur PHP 8.2) :

```sh
phpunit -c phpunit.pure.xml
```

La CI exécute ces contrôles sur PHP 8.2 et 8.5. Elle utilise JumPHP au commit `28ea932981fb0b6d8dc41f2c9739f941cb4befea` pour les tests d’intégration et les captures OpenGL. Le workflow WASM ajoute des clics desktop, des taps mobile, des reprises après mort/victoire et des captures des différentes étapes du boss. La publication `gh-pages` conserve son historique et utilise un push normal.

Dans un checkout JumPHP disposant de son runtime Docker, copier le package dans `games/slave-rift`, configurer le dépôt Composer local du moteur comme dans `.github/workflows/checks.yml`, puis :

```sh
bin/jumphp install slave-rift
bin/jumphp test slave-rift
JUMPHP_SCENE='App\Scene\PlayScene' bin/jumphp shot slave-rift 2
bin/jumphp build slave-rift
bin/jumphp webshot slave-rift 6 '[[1,"Enter"]]'
```

Le moteur logiciel reste un diagnostic vu du dessus ; le rendu normal est OpenGL/WebGL. La simulation, les collisions, l’intégration native et les interactions navigateur desktop/mobile ont des tests automatiques. Des captures réelles de la CI ont été examinées. Une partie humaine complète sur ordinateur et sur téléphone reste nécessaire : voir [la qualification](docs/qualification.md).

## Licence

Code, carte originale, maillages et aperçu : GPL-3.0-or-later. Assets CC0 et autres provenances : [assets/ASSETS.md](assets/ASSETS.md). JumPHP est un moteur indépendant sous licence MIT. Aucun nouveau sprite propriétaire n’a été ajouté.
