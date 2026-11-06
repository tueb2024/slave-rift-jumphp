# Intégration autorisée et mélodies par session

Le 6 novembre 2026, l’utilisateur autorise explicitement le push dans `main` et demande des mélodies différentes à chaque nouvelle session.

Le générateur précédent n’avait que deux motifs de six notes, transposés et harmonisés. Quatre notes intermédiaires sont maintenant composées indépendamment, en conservant les ancrages harmoniques. Une seed musicale séparée du gameplay est choisie à chaque nouvelle partie, y compris un replay et un nouveau Défi du jour. Le transport et le morceau restent continus entre maps.

`SessionMelody` compare le contour relatif des notes, donc une simple transposition ne suffit pas. Le candidat doit différer des 32 dernières mélodies. L’historique est enregistré dans `var/music-contours.json` via le Storage officiel (fichier natif, IndexedDB en WASM). Les démos gardent leur seed fixe. La garantie d’anti-répétition dépend de la conservation de cette sauvegarde et ne s’étend pas à une infinité de sessions ou à des appareils indépendants.

Vérifications : candidats répétés, 100 sessions avec sauvegarde/relecture d’historique, diversité de plus de 100 contours sur 256 seeds, consonance du contrepoint, banque de samples, déterminisme par seed musicale et RNG du gameplay inchangé. Le test navigateur vérifie qu’un replay garde la même map mais change de contour musical.
