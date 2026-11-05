# Version de test — difficulté progressive, musique et sprites

Intégration dans `main` autorisée par l'utilisateur le 5 novembre 2026. Base de travail : `feature/808-sampled-score` à `6b05027c80deed8134c3aa30558cb20fbd834b20`. `main` a été revérifié à `81daa940fb8cac4620d0d5c06e9c69add0cef209`, ancêtre direct de la branche ; l'intégration conserve tout son historique et ses assets.

## Essayer

Recharger le jeu depuis `main`, puis choisir **DÉFI DU JOUR** pour essayer l'équilibrage sur quatre maps. Les autres modes conservent leurs règles. La date UTC conserve les mêmes quatre seeds pour comparer les essais : le 5 novembre 2026, **33771 / 4262 / 40273 / 10764**.

La version comprend la musique TR-808 échantillonnée (53 fichiers PCM, lead, contrepoint et basse), les sept sprites d'armes animés, les cinq sprites de pouvoirs, le pouvoir exclusif et la poursuite persistante. Aucun build Blender ni téléchargement d'assets supplémentaire n'est nécessaire pour les utiliser. La banque audio était déjà dans `main` ; les derniers sprites et la poursuite sont repris de la branche de travail.

## Réglage retenu

| Map | Vagues avant le boss | Salle finale | Total | Plafond vivant | Groupe | Cadence |
|---|---|---:|---:|---|---|---|
| 1 | 10 / 12 / 14 | 8 | 44 | 4, puis 6 et 8 | 1 | 1,15 s au départ ; 0,85 s après le 8e kill ; 0,77 / 0,69 s aux vagues suivantes |
| 2 | 16 / 18 / 20 / 22 | 12 | 88 | 8 → 12 | 1, puis 2 | 0,95 → 0,80 s |
| 3 | 24 / 26 / 28 / 28 / 30 | 16 | 152 | 12 → 16 | 2 | 0,80 → 0,65 s |
| 4 | 32 / 34 / 36 / 36 / 38 / 40 | 20 | 236 | 16 → 20 | 2, puis 3 | 0,70 → 0,55 s |

Les pauses entre vagues durent respectivement 4 / 3,5 / 3 / 2,5 secondes. Le budget total passe de **1 064 à 520 ennemis**. Les sept armes se débloquent à **0 / 8 / 26 / 63 / 125 / 218 / 354 kills cumulés**. Les seuils sont rapprochés avec les budgets pour garder la double Gatling accessible sur la quatrième map.

Un plafond plein suspend les apparitions et conserve le budget restant. Les survivants ne disparaissent pas ; la vague doit être terminée pour progresser. Le rythme initial accélère avec le premier changement d'arme, évitant une cadence lente uniforme.

Les PV et dégâts ennemis, leur composition, l'XP, le tempo par niveau, les soins, le pouvoir exclusif, la géométrie et les règles des boss sont conservés. Il s'agit d'une première version à éprouver en jeu, sans prétendre valider l'équilibrage humain ni la cible de 12 à 18 minutes.

## Vérification

- **99 tests PHPUnit, 137 412 assertions**, dont contrôle de la saturation à quatre survivants, reprise d'apparition après une élimination, accélération au huitième kill et disponibilité des sept armes.
- **94 contrôles de simulation**, syntaxe PHP et synchronisation racine/package.
- Contrats audio : 53 PCM et empreintes vérifiés, canaux bornés et fermeture audio.
- Les assets audio et les 12 atlas d'arsenal sont présents et ont les mêmes SHA dans les deux arbres GitHub.
- La CI du parent a réussi sur PHP 8.2/8.5, le runtime natif SDL/OpenGL et le playtest WASM. La CI est relancée sur la version intégrée.

### Parties normales, dégâts actifs

| Profil | Première map | Suite de la campagne | Résultat final |
|---|---|---|---|
| Faible | Victoire à 3 min 04 s | Map 2 | Mort à 3 min 44 s cumulées |
| Moyen | Victoire à 2 min 30 s | Victoire map 2 à 5 min 54 s ; accès map 3 | Mort à 11 min 21 s cumulées |
| Bon | Victoire à 2 min 25 s | Map 2 | Mort à 4 min 07 s cumulées |

Avant cette correction, les trois profils mouraient sur la première map. Maintenant les trois la terminent, et le moyen atteint la troisième. Les politiques de bots sont imparfaites : le profil nommé « bon » peut mourir avant le moyen, en fonction de sa trajectoire, des choix et des attaques. Ce classement ne mesure pas directement la difficulté humaine.

Aucun timeout ni échec de recherche dans ces parties. La difficulté des maps suivantes reste à juger avec un joueur humain.

### Contrôle de progression sans dégâts

En neutralisant les dégâts uniquement dans le fixture de validation, les trois profils terminent les quatre maps, leurs quatre boss et leurs quatre trésors, avec **520 éliminations**, aucun survivant ni échec de navigation :

- Faible : **29 min 11 s**.
- Moyen : **25 min 23 s**.
- Bon : **28 min 42 s**.

Ces durées restent supérieures à la cible de 12–18 minutes et ne constituent pas une mesure de difficulté. Les déplacements, choix et recherches visuelles des bots contribuent fortement à leur longueur. Les JSON de parties, contrôle de progression et pression initiale sont dans [progressive/](progressive/).

```sh
php tools/sync-package.php --check
phpunit -c phpunit.pure.xml
php tools/simulation-test.php
php tools/sampler-contract-test.php
php tools/daily-pursuit-report.php 2026-11-05 > progressive-daily.json
php tools/daily-pursuit-report.php 2026-11-05 /tmp/progress.json --navigation-only > progressive-navigation.json
```
