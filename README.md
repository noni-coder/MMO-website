# Ensemble MMO — Site Internet

## Structure du projet

```
mmo-site/
├── index.html              ← Page principale
├── .htaccess               ← Sécurité Apache, cache, compression
├── css/
│   └── style.css           ← Feuille de style complète
├── js/
│   └── main.js             ← Carrousel, animations, formulaire AJAX
├── php/
│   ├── .htaccess           ← Protection des fichiers sensibles
│   ├── config.php          ← Identifiants SMTP (protégé)
│   ├── SmtpMailer.php      ← Classe d'envoi SMTP (protégé)
│   ├── csrf-token.php      ← Générateur de token anti-CSRF
│   └── send-mail.php       ← Traitement du formulaire
├── img/
│   ├── logo-white.png      ← Logo navigation/footer
│   ├── logo-dark.png       ← Logo héro (fond sombre)
│   ├── logo-main.png       ← Logo fond blanc
│   └── hero-poster.jpg     ← Image placeholder vidéo
└── video/                  ← À CRÉER pour la vidéo du concert
    └── concert.mp4         ← À AJOUTER (90 secondes)
```

## Déploiement via FileZilla sur OVH

1. Connectez-vous à votre hébergement OVH via FileZilla
2. Naviguez vers le dossier `www/` (racine du site)
3. **Uploadez TOUT le contenu** du dossier `mmo-site/` (pas le dossier lui-même)
4. Vérifiez que `index.html` est à la racine `www/`
5. **IMPORTANT** : les fichiers `.htaccess` sont des fichiers cachés — activez leur affichage dans FileZilla (Serveur > Forcer l'affichage des fichiers cachés)

## Configuration e-mail

L'adresse `contact@ensemble-mmo.fr` est déjà configurée dans `php/config.php`.

### Paramètres SMTP OVH
- **Serveur** : `ssl0.ovh.net`
- **Port** : `465` (SSL)
- **Utilisateur** : `contact@ensemble-mmo.fr`

### Si vous changez de mot de passe
Éditez `php/config.php` et modifiez la ligne :
```php
define('SMTP_PASS', 'VotreNouveauMotDePasse');
```

### Vérification post-déploiement
1. Allez sur votre site et testez le formulaire
2. Vérifiez la réception dans la boîte `contact@ensemble-mmo.fr`
3. Vérifiez que l'expéditeur reçoit la réponse automatique
4. Testez que `https://votre-site.fr/php/config.php` retourne une erreur 403

## Sécurité intégrée

- **Validation e-mail** : syntaxe + vérification DNS MX + blacklist des domaines jetables
- **Anti-bot** : champ honeypot invisible
- **Anti-CSRF** : token généré côté serveur
- **Rate-limiting** : 1 message/minute max, 5/heure max par IP
- **Nettoyage XSS** : toutes les entrées sont assainies
- **Protection fichiers** : config.php et SmtpMailer.php inaccessibles via HTTP

## Réponse automatique

Chaque personne qui envoie un message reçoit automatiquement un e-mail HTML :
> « Bonjour ! Merci de nous avoir contactés ! Nous vous répondons dans les 24h !
> À très vite ! Ensemble MMO — LA Team 😉 »

## Ajouter la vidéo du concert

1. Créez un dossier `video/` à la racine
2. Placez votre vidéo en `.mp4` (recommandé : codec H.264, ~720p, < 30 Mo)
3. Décommentez dans `index.html` :
```html
<source src="video/concert.mp4" type="video/mp4">
```

## Ajouter les photos

Remplacez les `<div class="img-placeholder">` dans la section « L'Orchestre » par :
```html
<img src="img/votre-photo.jpg" alt="Description">
```

## SSL/HTTPS

Si votre certificat SSL OVH est actif, décommentez dans `.htaccess` la section de redirection HTTP → HTTPS.
