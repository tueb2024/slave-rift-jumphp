# Slave Rift — Mecha combat overhaul

FPS PHP/JumPHP : méchas industriels originaux, tirs automatiques rythmiques, progression à sept paliers, secondaires séquencées et musique procédurale. Branche de travail : **`feature/mecha-combat-overhaul`**, issue de `main` **`ec4f45d920a70810a5c878f8cf8fcb3a2787e89c`**. Aucune fusion ni publication automatique depuis cette branche.

## Jouer

- **JOUER** : carte procédurale normale ; boss siège, modules protecteurs, cœur vulnérable, trésor.
- **DÉFI DU JOUR** : une campagne de **quatre maps successives**, identique pour tous à date UTC donnée. Un boss et un trésor par map ; victoire globale après la quatrième seulement. Écran intermédiaire **MAP SUIVANTE**. XP, armes, upgrades, score, PV maximum, cooldowns et temps sont conservés ; récupération de 35 % des PV manquants.
- **HORDE** : arène 40 × 40, jusqu’à 200 ennemis ; boss aux vagues 5, 10, 15 puis toutes les cinq vagues. Une seule rencontre à la fois ; la vague attend la destruction du siège.
- Z/W, S ou haut/bas : avancer/reculer ; Q/A, D : déplacement latéral ; gauche/droite : tourner ; Espace/B : sauter ; P/Échap/Select : pause. Le joystick mobile et les boutons SAUT/PAUSE restent disponibles. Menus et upgrades : toucher, 1/2/3 ou flèches puis Entrée/A.

Les méchas gardent leur couleur de classe : **runner jaune/orange**, **guardian bleu/cyan**, **enforcer carmin**, **brute magenta**. Les flashs, étincelles, fumée critique et effondrements signalent les dégâts séparément. Les enforcers annoncent une visée verrouillée que l’on peut esquiver ; les brutes préparent leur frappe lourde. Le guardian fait obstacle par sa largeur et son blindage, le runner réduit rapidement la distance.

Le siège possède trois modules protecteurs. Détruisez-les pour ouvrir le cœur pendant six secondes (durée réduite dans les rencontres Horde avancées). Son châssis reste magenta dans les trois phases. Les zones au sol annoncent ses frappes ; bougez avant leur impact.

## Arsenal et musique

| Stade | Gameplay conservé | Apparence | Niveau normal / campagne |
|---|---|---|---|
| 1 | Colt | Pistolet | 1 / 1 |
| 2 | Double Colt | Deux pistolets | 4 / 3 |
| 3 | MP44 | Pistolet-mitrailleur | 8 / 5 |
| 4 | Double MP44 | Deux automatiques | 12 / 8 |
| 5 | BAR | Fusil d’assaut | 16 / 11 |
| 6 | Gatling | Mitrailleuse multicanon | 20 / 14 |
| 7 | Double Gatling | Deux mitrailleuses | 24 / 17 |

Le bazooka est une secondaire ; il ne remplace pas la double Gatling. Les roquettes voyagent jusqu’à leur point d’impact puis infligent des dégâts de zone avec contrôle des murs. L’artillerie ajoute une charge plus lente et une explosion plus large. Le sniper privilégie un enforcer ou une brute visible, jusqu’à 40 unités, et matérialise son tir par un rayon cyan. Le lance-flammes dessine un jet continu, limité par la géométrie, pendant ses dégâts progressifs.

Les cinq secondaires restent en haut à droite. Le scheduler FIFO espace leurs départs d’au moins une croche ; une arme prête attend sans perdre sa recharge, et un jet déjà actif continue. Les paliers, barème XP **2/3/4/6** et +2 BPM par niveau sont conservés. Les soins sont moins généreux : +4 entre vagues, 14 PV tous les 14 kills, vitalité proposée tous les trois niveaux seulement (+15 maximum, soin de 25).

`RhythmClock` reste le transport commun aux tirs et à la musique. Le sampler utilise le mixer existant et une petite banque de notes synthétiques. Un RNG musical séparé choisit tonique, mode et motif. Des phrases reconnaissables se répètent, varient et passent sur une basse harmonique simple ; une seconde voix utilise un registre inférieur, des attaques complémentaires et des consonances vérifiées aux temps structurels. Percussions et tension augmentent au combat ; une cadence signale la victoire.

## Générer les modèles et sprites sur Windows

Depuis le dépôt :

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\blender\build_all_assets.ps1
```

Si Blender n’est pas détecté :

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\blender\build_all_assets.ps1 -Blender "C:\Program Files\Blender Foundation\Blender 5.2\blender.exe"
```

Le pipeline crée les modèles `.blend`, PNG transparents, atlas et icônes puis synchronise les ressources runtime avec `games/slave-rift/`. Il produit un manifeste SHA-256 et ne modifie aucune ressource historique. **Blender n’a pas été exécuté dans l’environnement d’édition** : des atlas originaux de secours sont déjà intégrés et chargés par le jeu. Détails, formats et limites : [pipeline](docs/mecha-art-pipeline.md).

## Défi quotidien

Quatre seeds indépendantes sont dérivées de `slave-rift/daily/v2/map/{stage}/{date}` par un hash borné identique en PHP 32/64 bits. `CampaignRun::daily(date, serverSeeds, id)` accepte aussi quatre seeds fournies à l’avenir par un serveur. Aucun backend n’est nécessaire. Une campagne conserve sa date au replay, même après minuit.

Les parcours comptent **12, 20, 30 et 44 salles principales**. La borne du générateur passe à 48 ; les salles et escaliers ne sont pas étirés. Les boss ont 1 000 / 1 200 / 1 450 / 1 750 PV ; la composition, le budget, la cadence des rencontres et les dégâts portent l’essentiel de la difficulté. Cible : 8–12 minutes pour un profil moyen performant. Les bots sont des politiques imparfaites, pas une prédiction des taux de victoire humains.

## Vérifier

```sh
php tools/simulation-test.php
phpunit -c phpunit.pure.xml
php tools/sync-package.php --check
php tools/campaign-sim.php 10 2-3-4-6 .35 compact > campaign-report.json
php tools/horde-stress.php
python tools/blender/verify_assets.py
```

Le dépôt d’auteur est à la racine ; **`games/slave-rift/`** est le package JumPHP. Après toute modification : `php tools/sync-package.php`. Les `.blend` et rendus intermédiaires restent hors du package ; seuls les PNG/JSON générés servent au runtime. Aucun GLB inutilisé.

La CI teste PHP 8.2/8.5, le runtime natif SDL/OpenGL et WASM, les clics/taps desktop/mobile, la succession des maps et la charge 25/50/100/200. Les mesures CPU sont séparées des mesures de frames Chromium/SwiftShader. Une exécution headless ne valide ni un téléphone physique ni le rendu CUDA sur votre GTX. Voir [qualification et livraison](docs/mecha-overhaul.md).

Code et assets procéduraux originaux : GPL-3.0-or-later. Provenances historiques : [assets/ASSETS.md](assets/ASSETS.md).

La croissance de surface est vérifiée à chaque transition : si le tirage contient trop de couloirs étroits, des modules sont remplacés, dans un ordre déterministe, par des salles standards plus grandes. Les dimensions des modules ne changent jamais. Les quatre seeds restent fixées avant la partie.
