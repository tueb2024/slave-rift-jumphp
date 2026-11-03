# Pipeline méchas / armes

## Contrat de rendu

L’audit de `World`, `EnemyAtlas`, `WeaponRenderer` et du renderer JumPHP confirme le chargement de PNG transparents, rectangles source et billboards à huit directions. Aucune importation glTF ou animation squelettique n’est nécessaire. Le jeu conserve son monde OpenGL/WebGL et superpose les sprites SDL.

- Méchas : atlas **768 × 1120**, cellules **96 × 112**, huit colonnes. Lignes 0 idle, 1–4 marche, 5–7 attaque, 8 impact, 9 destruction. Pour les états vivants les colonnes représentent les directions ; en ligne destruction elles représentent huit instants d’affaissement, vus de face.
- Boss siège : même contrat, châssis élargi, deux pods d’artillerie, deux canons et cœur optique. Sa couleur reste magenta ; modules et cadre du cœur expriment la protection.
- Armes : atlas **576 × 96**, quatre cellules **144 × 96** : repos, retour, tir, recul. Les doubles réutilisent le modèle avec deux affichages miroirs déjà présents dans le moteur.
- HUD : PNG **32 × 16**, normal et assombri pour chaque arme ; le chargeur consomme les cinq secondaires acquises en haut à droite.

## Sources et exécution

`tools/blender/art_spec.py` décrit proportions, plaques, joints, pistons, blindages, capteurs, canons et armes. La palette `PALETTE` est modifiable par classe ; aucun paramètre de PV n’entre dans cette palette.

`generate_mechas.py`, `generate_bosses.py` et `generate_weapons.py` construisent les modèles Blender originaux avec cubes biseautés et cylindres octogonaux. `render_sprites.py` organise caméra orthographique, éclairage fixe, transparence, poses, directions, packing et export. Les `.blend` sont conservés dans `assets/generated/models/` pour retouche, mais ne sont pas chargés par le jeu.

Depuis la racine sur Windows PowerShell :

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\blender\build_all_assets.ps1 -Blender "C:\Program Files\Blender Foundation\Blender 5.2\blender.exe"
```

Sans `-Blender`, le script cherche le PATH puis `Program Files\Blender Foundation`. `-Samples 16` règle Cycles si utilisé. EEVEE est préféré lorsque disponible ; sinon Cycles essaie CUDA puis repasse au CPU avec un message explicite. Une GTX ne nécessite pas OptiX. La compatibilité Blender 5.2.3 et les cadrages finaux sont à vérifier sur la machine Windows : cet environnement ne dispose pas de Blender.

Une exécution produit les 4 méchas, le siège et 9 armes : pistolet, automatique léger, fusil d’assaut, Gatling, bazooka, sniper, lance-flammes, artillerie, impulsion. L’impulsion conserve sa mécanique radiale ; son icône partage les mêmes matériaux que l’arsenal.

Aucune dépendance Python externe dans Blender. Le mode développeur de secours `python tools/blender/fallback_assets.py` nécessite Pillow et produit des rendus facettés des primitives paramétriques, avec géométrie cylindrique simplifiée en blocs. Ce sont les PNG livrés avant exécution Blender, pas des captures provenant de Blender. La syntaxe de tous les modules est compilée en CI ; cela ne prétend pas vérifier l’API `bpy` sans Blender.

## Manifeste, erreurs et relance

Tous les fichiers produits restent dans `assets/generated/`. Les dossiers historiques ne sont jamais écrasés. Les frames temporaires sont dans `assets/generated/.work/` ; leur suppression est limitée à ce dossier. Relancer reconstruit les mêmes noms. Les maillages des poses précédentes sont libérés pour borner la mémoire de génération.

Le manifeste contient version, renderer, dimensions et SHA-256 des PNG et modèles produits. PowerShell vérifie les hashes, puis copie seulement les PNG et le manifeste dans `games/slave-rift/assets/generated/`. Un code de sortie Blender non nul ou un fichier absent provoque un échec explicite. Un ancien manifeste ne transforme pas une exécution échouée en succès.

Vérification autonome :

```sh
python -m py_compile tools/blender/*.py
python tools/blender/verify_assets.py
php tools/sync-package.php --check
```

Les PNG de secours contiennent 32 ressources : cinq atlas de méchas/boss, neuf atlas d’armes, dix-huit icônes. Le jeu n’utilise actuellement que les quatre atlas principaux et cinq paires d’icônes ; les atlas FPS des secondaires sont disponibles pour extension, sans imposer de rendu inutile. Les explosions, lasers et jets sont tracés par le renderer et bornés en simulation : aucun gros atlas FX supplémentaire.

## Qualification visuelle

À vérifier : transparence, pieds sur le sol, orientation zéro face au joueur, cohérence des huit directions, visibilité à 3/15 mètres, contraste dans la pénombre, silhouette à 25/50/100/200 ennemis et armes doubles sans masquer les menaces. Conserver les PNG de secours tant que les rendus Blender ne sont pas validés. Vérifier les modèles `.blend` avant d’en faire une référence artistique définitive.
