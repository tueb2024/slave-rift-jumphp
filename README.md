# Slave Rift — Canon scié, boss et portes procédurales

FPS PHP/JumPHP : méchas industriels, sprites d’armes et de pouvoirs animés, tirs automatiques rythmiques et musique échantillonnée. Version de l’issue #5 intégrée dans `main` avec l’autorisation explicite du 6 novembre 2026. [Variation musicale par session](docs/issue-5/session-melodies.md). [Rapport, mesures et captures](docs/issue-5/REPORT.md).

Pour une campagne aléatoire de quatre maps de plus en plus grandes et denses : choisir **JOUER**. Le parcours passe de **12 à 20, 30 puis 44 salles principales**, avec **54 / 99 / 168 / 268 ennemis** et un boss par map. XP, armes et améliorations sont conservés entre les maps ; victoire après la quatrième. Pour une campagne identique pour tous à date donnée : choisir **DÉFI DU JOUR**. Dans le Défi du jour, le départ comporte 10 ennemis, avec au plus 4 survivants simultanés ; la cadence accélère après le double Colt. Le budget complet est de 520 ennemis. Les archétypes et les boss évoluent dans tous les modes ; les portes séquencent les rencontres procédurales. La Horde garde son arène ouverte. [Windows prêt à tester](https://github.com/tueb2024/slave-rift-jumphp/actions/workflows/desktop-playtest.yml?query=branch%3Amain) · [Build WASM](https://github.com/tueb2024/slave-rift-jumphp/actions/workflows/wasm-playtest.yml?query=branch%3Amain).

## Jouer

- **JOUER** : campagne aléatoire de **quatre maps** ; 3 / 4 / 5 / 6 rencontres avant la vague finale et le boss. Les salles gardent leurs dimensions, le parcours s’allonge et les arrivées ennemies s’accélèrent. L’équipement est conservé, avec récupération de 35 % des PV manquants entre les maps. **REJOUER LES 4 MAPS** reprend le même parcours depuis le début ; **NOUVELLE CAMPAGNE** change le parcours. [Plans, captures et validation](docs/normal-progression/REPORT.md).
- **DÉFI DU JOUR** : une campagne de **quatre maps successives**, identique pour tous à date UTC donnée. Un boss et un trésor par map ; victoire globale après la quatrième seulement. Écran intermédiaire **MAP SUIVANTE**. XP, armes, upgrades, score, PV maximum, cooldowns et temps sont conservés ; récupération de 35 % des PV manquants.
- **HORDE** : arène 40 × 40, jusqu’à 200 ennemis ; boss aux vagues 5, 10, 15 puis toutes les cinq vagues. Une seule rencontre à la fois ; la vague attend la destruction du siège.
- Z/W, S ou haut/bas : avancer/reculer ; Q/A, D : déplacement latéral ; gauche/droite : tourner ; Espace/B : sauter ; P/Échap/Select : pause. Canon scié initial : **F** ou **A** à la manette déclenche le canon scié ; sur mobile, toucher **TIR** dans le jeu. Il recharge automatiquement. Le joystick mobile et les boutons SAUT/PAUSE restent disponibles. Menus et upgrades : toucher, 1/2/3 ou flèches puis Entrée/A.

Les méchas gardent leur couleur de classe : **runner jaune/orange**, **guardian bleu/cyan**, **enforcer carmin**, **brute magenta**. Les flashs, étincelles, fumée critique et effondrements signalent les dégâts séparément. Les enforcers annoncent une visée verrouillée que l’on peut esquiver ; les brutes préparent leur frappe lourde. Le guardian fait obstacle par sa largeur et son blindage, le runner réduit rapidement la distance.

Le boss est désormais un marcheur magenta mobile, animé dans huit directions. Il avance, strafe et annonce une rafale en éventail : rejoignez la petite salle adjacente pendant le barrage, puis profitez de sa récupération pour attaquer. Toutes les armes lui infligent des dégâts ; son blindage réduit les dégâts pendant le barrage. Ses PV passent à **9 000 / 24 000 / 64 000 / 150 000** sur les quatre maps. La Horde possède aussi un refuge et des boss de plus en plus résistants. [Captures et simulations](docs/manual-boss-rebalance/REPORT.md).

Les portes de rencontre sont physiquement fermées vers l’avant tant que la vague locale n’est pas terminée. Elles s’ouvrent avec un signal ; la vague suivante attend la zone suivante. Les survivants poursuivent le joueur par les vraies portes et escaliers. Les embranchements BONUS restent accessibles.

## Arsenal et musique

Chaque nouvelle partie commence au **canon scié manuel** (F, clic, A ou bouton tactile TIR). Les autres armes sont proposées dans les choix de montée de niveau, aux paliers suivants ; aucune acquisition automatique par kills. Elles restent acquises entre les quatre maps et sont réinitialisées au replay.

| Arme proposée | Niveau minimum |
|---|---|
| Colt | 4 |
| Double Colt | 9 |
| MP44 | 15 |
| Double MP44 | 23 |
| BAR | 33 |
| Gatling | 45 |
| Double Gatling | 59 |

Le bazooka est une secondaire ; il ne remplace pas la double Gatling. Les roquettes voyagent jusqu’à leur point d’impact puis infligent des dégâts de zone avec contrôle des murs. L’artillerie ajoute une charge plus lente et une explosion plus large. Le sniper privilégie un enforcer ou une brute visible, jusqu’à 40 unités, et matérialise son tir par un rayon cyan. Le lance-flammes dessine un jet continu, limité par la géométrie, pendant ses dégâts progressifs.

Le joueur choisit **deux secondaires au maximum**, conservées jusqu’à la fin de la partie : lance-flammes, roquettes guidées, précision, artillerie, IEM ou impulsion. Tant qu’une place est libre, les montées de niveau peuvent proposer une acquisition ou un bonus classique. **Une fois les deux places prises : aucun remplacement, aucune troisième secondaire, Puissance, Cadence, Aimant ou Vitalité, ainsi que les acquisitions principales encore disponibles aux paliers ci-dessus.** Les deux recharges sont visibles en haut à droite ; le scheduler FIFO espace leurs départs d’au moins une croche et laisse continuer un jet déjà actif. Le duo, les recharges et les effets actifs sont conservés entre les maps.

Le lance-flammes projette désormais un jet texturé depuis le premier plan vers la profondeur de la scène, suivant la caméra et arrêté par les murs. Ses règles de dégâts, portée et durée sont conservées. Dans **JOUER** et le **Défi du jour**, certaines petites salles et salles bonus contiennent trois trousses de **16 PV** : elles restent sur place, ne sont pas aspirées par l’aimant et ne sont pas consommées à PV pleins. **Les ennemis de ces deux modes ne lâchent plus de soins**, uniquement de l’XP. La Horde conserve ses anciens drops de soins. Les +4 PV entre vagues, Vitalité et la récupération entre maps restent disponibles. [Comparatifs du jet, salles de soins et duo permanent](docs/flame-supplies-duo/REPORT.md).

Le barème XP **2/3/4/6** et +2 BPM par niveau sont conservés. Tous les modes utilisent la même courbe de niveaux : `6 + floor(0,7 × (niveau − 1))` XP pour le niveau suivant. Les intervalles entre propositions d’armes augmentent : 5, 6, 8, 10, 12 puis 14 niveaux.

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

Les parcours comptent **12, 20, 30 et 44 salles principales**. La borne du générateur passe à 48 ; les salles et escaliers ne sont pas étirés. Les boss ont 9 000 / 24 000 / 64 000 / 150 000 PV ; la composition, le budget, la cadence des rencontres et les dégâts portent l’essentiel de la difficulté. La cible de 12–18 minutes pour le Défi du jour reste à éprouver par des essais humains ; les durées des bots sont publiées séparément. Les bots sont des politiques imparfaites, pas une prédiction des taux de victoire humains.

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

