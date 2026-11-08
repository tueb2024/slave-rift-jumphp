# Slave Rift — Canon scié, boss et portes procédurales

FPS PHP/JumPHP : méchas industriels, sprites d’armes et de pouvoirs animés, tirs automatiques rythmiques et musique échantillonnée. Version de l’issue #5 intégrée dans `main` avec l’autorisation explicite du 6 novembre 2026. [Variation musicale par session](docs/issue-5/session-melodies.md). [Rapport, mesures et captures](docs/issue-5/REPORT.md).

Pour une campagne aléatoire de quatre maps de plus en plus grandes et denses : choisir **JOUER**. Le parcours passe de **12 à 20, 30 puis 44 salles principales**, avec **54 / 99 / 168 / 268 ennemis** et un boss par map. XP, armes et améliorations sont conservés entre les maps ; victoire après la quatrième. Pour une campagne identique pour tous à date donnée : choisir **DÉFI DU JOUR**. Dans le Défi du jour, le départ comporte 10 ennemis, avec au plus 4 survivants simultanés ; la cadence accélère après le double Colt. Le budget complet est de 520 ennemis. Les archétypes et les boss évoluent dans tous les modes ; les portes séquencent les rencontres procédurales. La Horde garde son arène ouverte. [Windows prêt à tester](https://github.com/tueb2024/slave-rift-jumphp/actions/workflows/desktop-playtest.yml?query=branch%3Amain) · [Build WASM](https://github.com/tueb2024/slave-rift-jumphp/actions/workflows/wasm-playtest.yml?query=branch%3Amain).

## Jouer

- **JOUER** : campagne aléatoire de **quatre maps** ; 3 / 4 / 5 / 6 rencontres avant la vague finale et le boss. Les salles gardent leurs dimensions, le parcours s’allonge et les arrivées ennemies s’accélèrent. L’équipement est conservé, avec récupération de 35 % des PV manquants entre les maps. **REJOUER LES 4 MAPS** reprend le même parcours depuis le début ; **NOUVELLE CAMPAGNE** change le parcours. [Plans, captures et validation](docs/normal-progression/REPORT.md).
- **DÉFI DU JOUR** : une campagne de **quatre maps successives**, identique pour tous à date UTC donnée. Un boss et un trésor par map ; victoire globale après la quatrième seulement. Écran intermédiaire **MAP SUIVANTE**. XP, armes, upgrades, score, PV maximum, cooldowns et temps sont conservés ; récupération de 35 % des PV manquants.
- **HORDE** : arène 40 × 40, jusqu’à 200 ennemis ; boss aux vagues 5, 10, 15 puis toutes les cinq vagues. Une seule rencontre à la fois ; la vague attend la destruction du siège.
- Z/W, S ou haut/bas : avancer/reculer ; Q/A, D : déplacement latéral ; gauche/droite : tourner ; Espace/B : sauter ; P/Échap/Select : pause. Boss : **F** ou **A** à la manette déclenche le canon scié ; sur mobile, toucher **TIR** dans le jeu. Il recharge automatiquement. Le joystick mobile et les boutons SAUT/PAUSE restent disponibles. Menus et upgrades : toucher, 1/2/3 ou flèches puis Entrée/A.

Les méchas gardent leur couleur de classe : **runner jaune/orange**, **guardian bleu/cyan**, **enforcer carmin**, **brute magenta**. Les flashs, étincelles, fumée critique et effondrements signalent les dégâts séparément. Les enforcers annoncent une visée verrouillée que l’on peut esquiver ; les brutes préparent leur frappe lourde. Le guardian fait obstacle par sa largeur et son blindage, le runner réduit rapidement la distance.

Le siège possède trois modules protecteurs. Détruisez-les pour ouvrir le cœur pendant six secondes (durée réduite dans les rencontres Horde avancées). Son châssis reste magenta dans les trois phases. Il annonce la position visée puis lance de vrais projectiles esquivables, en éventails selon la phase. **Seul le canon scié retire les PV du cœur** : approchez à moins de 7 unités et déclenchez vos tirs pendant l’ouverture. Le canon scié est garanti à chaque boss, y compris quotidien map 1 et Horde ; il ne remplace ni les sept armes principales ni le pouvoir exclusif.

Les portes de rencontre sont physiquement fermées vers l’avant tant que la vague locale n’est pas terminée. Elles s’ouvrent avec un signal ; la vague suivante attend la zone suivante. Les survivants poursuivent le joueur par les vraies portes et escaliers. Les embranchements BONUS restent accessibles.

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

Les cinq secondaires restent en haut à droite. Le scheduler FIFO espace leurs départs d’au moins une croche ; une arme prête attend sans perdre sa recharge, et un jet déjà actif continue. Le barème XP **2/3/4/6** et +2 BPM par niveau sont conservés. Dans le Défi du jour, les armes avancent aux seuils de kills cumulés **0 / 8 / 26 / 63 / 125 / 218 / 354** ; le tableau de niveaux ci-dessus concerne la progression XP normale / campagne historique. Les soins sont moins généreux : +4 entre vagues, 14 PV tous les 14 kills, vitalité proposée tous les trois niveaux seulement (+15 maximum, soin de 25).

Chaque nouvelle partie choisit une mélodie dont le contour diffère des **32 dernières parties**, grâce à un historique musical local. Cela inclut les replays et les Défis du jour ; les seeds de gameplay du défi restent identiques. Cette mémoire dépend de la conservation des sauvegardes locales (desktop ou IndexedDB du navigateur) et ne promet pas une unicité infinie. Les transitions entre les quatre maps ne changent pas de morceau.

Le lead commence au **niveau 2**, le contrechant au **niveau 6**. `RhythmClock` reste le transport commun aux tirs et à la musique. Le sampler utilise le mixer existant et une banque de 53 fichiers PCM : percussions TR-808 échantillonnées, lead, contrepoint et basse. Les samples et leurs provenances sont fournis dans le dépôt ; aucune génération n’est requise pour jouer. Un RNG musical séparé choisit tonique, mode et motif. Des phrases reconnaissables se répètent, varient et passent sur une basse harmonique simple ; une seconde voix utilise un registre inférieur, des attaques complémentaires et des consonances vérifiées aux temps structurels. Percussions et tension augmentent au combat ; une cadence signale la victoire.

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

Les parcours comptent **12, 20, 30 et 44 salles principales**. La borne du générateur passe à 48 ; les salles et escaliers ne sont pas étirés. Les boss ont 2 200 / 2 800 / 3 500 / 4 300 PV ; la composition, le budget, la cadence des rencontres et les dégâts portent l’essentiel de la difficulté. La cible de 12–18 minutes pour le Défi du jour reste à éprouver par des essais humains ; les durées des bots sont publiées séparément. Les bots sont des politiques imparfaites, pas une prédiction des taux de victoire humains.

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

