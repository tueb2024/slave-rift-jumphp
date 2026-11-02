# Audit et validation RC

Base de travail : `main`, commit `9ea6094`, arbre propre au clonage. Branche de préparation : `release/playable-rc`.

## Audit initial

| Élément | Blocage observé à la lecture |
|---|---|
| Démarrage | Partie immédiatement active ; pas d’accueil. README encore consacré à un ancien MVP. |
| Desktop | Actions clavier/manette raccordées ; autotir rythmique et renderer récent à préserver. |
| Web/mobile | Le moteur possède déjà Touch, Mouse, joystick web et contrôles virtuels. La scène ne lit pas les taps/clics des menus. |
| Pause | P uniquement dans la scène ; pas de commande visible ni de reprise/restart tactile. |
| Mort/restart | Écran et action restart présents, sans bouton cliquable. |
| XP | Collecte courte et coût rapidement croissant : armes avancées tardives. |
| Armes | Échelle Colt → double Gatling présente. Compensation de puissance injectée par réflexion dans les rangs privés. |
| Difficulté | Run applique un scaling PV, puis DifficultyDirector remplace le profil à l’apparition en tenant compte de la puissance du joueur. Cadence, budgets et composition augmentent simultanément. |
| Niveau | Portes de jonctions sans salle ; épaules d’escaliers ouvertes. Les tournants du même côté peuvent revenir vers des zones précédentes. |
| Objectif | Flèche directe vers le trésor, pouvant indiquer une direction à travers les murs. |
| Salle/vague finale | Entrée et nettoyage raccordés dans PlayScene ; les ennemis lointains peuvent bloquer le nettoyage. |
| TreasureDirector | États jusqu’à COMPLETE existants ; l’orchestration s’arrête à BOSS_READY. |
| BossController | Cycle orbes/vulnérabilité/phases déterministe existant, sans activation, attaques, ciblage ou rendu dans la partie. |
| Ancrage du boss | Les ancres latérales « wing » écrasent l’ancre centrale dans l’assembleur. |
| Trésor/victoire/rejouer | Aucun coffre ni écran de victoire raccordé. |
| Français | HUD, améliorations, mort et consignes partiellement en anglais. |
| Synchronisation/CI | Synchronisation à sens unique ; tests purs partiels et smokes GL existants. Pas de parcours tactile automatique. |

## Corrections

L’accueil, la pause, les améliorations et les écrans de fin utilisent les coordonnées Mouse du moteur. Le repli Touch convertit les coordonnées SDL normalisées via la letterbox du renderer. Aucune API tactile nouvelle n’est supposée. Le joystick et les boutons HTML du moteur sont configurés dans game.yaml. Les nouveaux tests moteur couvrent Mouse, Touch, confirmation clavier, pause et restart ; le script navigateur exerce les vrais taps DOM sur le build WASM.

Run possède désormais l’objectif et le BossController. Les transitions se produisent dans les pas de simulation : `exploring → final_wave → boss_ready → boss → treasure_ready → complete`, puis l’état de partie `won`. Le nettoyage final concerne la salle finale ; les ennemis restés ailleurs sont retirés sans score ni XP, et les nouvelles apparitions y sont confinées. Ce choix évite une chasse obligatoire dans les couloirs, sans exiger de navigation globale des ennemis. Les vagues ordinaires ne se superposent plus à cause d’un délai de 26 secondes.

Le boss mural utilise le monstre lourd CC0 déjà chargé par EnemyAtlas. Trois orbes visibles sont ciblées par l’autotir ; leur destruction ouvre six secondes de vulnérabilité. Les armes conservent leurs séquences rythmiques et leur renderer. Les attaques du boss annoncent une zone au sol pendant 1,1 seconde ; ses trois phases accélèrent leur fréquence et augmentent les dégâts. Les orbes reviennent après une fenêtre expirée. La victoire n’arrive qu’après sa défaite et l’approche du coffre, avec bonus unique de 10 000 points. Le boss dynamique reste hors périmètre.

La courbe utilise les poids PV 1/2/3/6 et un coefficient de vague unique à l’apparition. DifficultyDirector conserve ses méthodes publiques pour compatibilité, sans mutation adaptative des ennemis ni injection de puissance. La compensation des armes est calculée au tir. Les dégâts ennemis augmentent de 1,5 % par vague, la vitesse de 0,3 %, avec un plafond de 20 incréments. Les enforcers infligent 6 dégâts de base toutes les 2,4 secondes. La vitalité plafonne à 180 PV, puis soigne ; elle ne peut plus rendre les longues parties invulnérables par croissance illimitée des PV.

Les tournants alternent leurs directions pour garder un parcours qui avance. Les branches bonus qui recouvriraient une autre salle sont omises ; leurs ouvertures sont alors fermées. Les escaliers ont des épaules et linteaux. La flèche suit les portes connectées. Les apparitions procédurales exigent une ligne de vue et un sol valide, afin de ne pas compter sur une navigation complexe derrière des murs. Les ressources GL sont libérées à la reconstruction de la carte pour éviter une fuite au fil des restarts.

## Contrôles exécutés localement

- Syntaxe PHP des sources, outils, tests et package.
- `php tools/simulation-test.php` : 85 contrôles.
- PHPUnit pur : 45 tests et 3 247 assertions ; collision et marche sur 16 seeds, cycle final avec vrais tirs rythmiques, menace létale du boss, respawn des orbes, victoire figée/bonus unique, courbe PV, dégâts réels du double MP44, plafond de vitalité, parité du package et simulations de référence.
- `php tools/sync-package.php` puis `--check` : fichiers sources synchronisés. Le contrôle refuse aussi les fichiers supplémentaires dans les arbres runtime du package ; les anciennes archives de patchs sont conservées.
- `node --check tools/rc-browser-test.cjs` et lecture des API du moteur épinglé.
- Simulation multi-profils : `php tools/balance-sim.php 10 360 both` ; résultats détaillés dans `docs/balance-report.json`.

Les paramètres du jeu sont communs aux profils. Les délais de réaction sont 0,65/0,35/0,15 s ; les profils se distinguent aussi par la précision, l’attention, les hésitations, la vitesse de déplacement et la collecte. Le scénario arène est un contrôle de débit de combat ; il ne mesure pas la recherche du chemin. Un timeout reste signalé comme play/upgrade et n’est jamais compté comme victoire. Les essais sont permissifs : ils ne prouvent pas une tension humaine suffisante et ne justifient pas de durcir automatiquement le jeu.

## Vérification restant nécessaire avant qualification RC

Ce conteneur n’a pas Docker ni les extensions SDL/OpenGL de JumPHP. La tentative `bin/jumphp test slave-rift` échoue au démarrage du runtime : `docker: command not found`. Aucun test natif, build WASM ou screenshot réel de cette modification n’est donc déclaré validé ici.

Les workflows complètent ces vérifications après push de `release/playable-rc`, sans publication gh-pages depuis cette branche : intégration native, captures GL et navigateur desktop/mobile. Le test navigateur emploie des fixtures de mort et de victoire pour tester les boutons, et des fixtures du boss pour capturer son rendu ; le combat et les transitions réels sont testés séparément dans Run. Les images doivent encore être examinées, puis une partie doit être jouée sur ordinateur et téléphone. Le logiciel sans GL conserve son diagnostic vu du dessus et les objectifs, mais ne remplace pas le rendu FPS normal.

Un fichier historique inutilisé `assets/sprites/weapons/colt45.png` n’est pas une image PNG valide. Il n’est référencé par aucun renderer actuel ; les quatre atlas `fps-*.png` utilisés sont valides. Il n’a pas été remplacé dans cette passe pour préserver les sources d’assets existantes.

Aucun push ni changement sur main n’est nécessaire pour consulter ces changements localement. La branche de travail reste séparée ; sa publication doit suivre l’annonce des SHA exacts.

## Échantillon de simulation (10 seeds par profil et scénario, limite 360 s)

| Profil | Salle finale / boss / victoire (parcours) | Niveau moyen à la vague 7 (arène) | PV moyens à la vague 7 (arène) |
|---|---|---:|---:|
| faible | 90 % / 90 % / 90 % | 12.3 | 124.8 |
| moyen | 100 % / 100 % / 100 % | 15.1 | 135.8 |
| bon | 100 % / 100 % / 100 % | 15.7 | 136.0 |

Ces valeurs décrivent uniquement ces politiques d’entrée. Les délais, erreurs et choix d’amélioration ne constituent pas un modèle validé de joueurs humains. Un profil faible reste notamment bloqué dans sa navigation sur une seed, alors que le test de marche sans combat traverse cette seed.
