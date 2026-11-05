# Sessions procédurales, spawns proches et musique indépendante

## Jouer
- « JOUER » lance une campagne de **3 ou 4 cartes**, choisie à chaque nouvelle partie. Chaque carte utilise une **seed nouvelle, aléatoire, unique au sein de la campagne**, distincte du Défi du jour ; les cartes sont construites via LevelGraph/LevelAssembler.
- Les paramètres de la carte sont chargés au changement de niveau. Progression, niveau, XP, armes, pouvoir équipé, score et PV sont transférés par CampaignRun avec la récupération existante ; chaque carte possède boss et trésor.
- « NOUVELLE CAMPAGNE ALÉATOIRE » et « RECOMMENCER » depuis la pause créent une nouvelle campagne. Les entrées explicites de fixtures restent des cartes isolées pour préserver les tests. Le Défi du jour conserve ses quatre seeds fixes pour la comparaison.

## Proximité des ennemis du Défi du jour
- Priorité aux points d'apparition à environ 7,5 unités du joueur, avec une portée maximale de **18 unités** au lieu de 34.
- Un point à plus de 11,5 unités n'est retenu qu'après recherche de positions locales viables (4,7 à 10 unités, 60 essais).
- Le champ de vision physique, l'absence de collision, les différences de hauteur, la séparation des ennemis et la salle finale restent contraignants ; si les points locaux ne conviennent pas, un point sûr plus lointain peut être retenu, sans dépasser 18 unités.
- Aucun survivant n'est supprimé ; les plafonds, les budgets et la poursuite persistante sont conservés.

## Identité musicale
- Chaque démarrage (Jouer / Horde / Commencer le Défi du jour) choisit une **seed musicale indépendante de la carte**. La première phrase ne répète pas exactement celle du lancement précédent dans la même instance du jeu.
- La seed musicale est constante entre toutes les cartes de la même campagne et conserve le transport rythmique ; le Défi du jour garde sa géométrie reproductible tout en renouvelant sa composition.
- Les entrées de démonstration continuent d'utiliser des seeds fixes pour les tests automatisés.
