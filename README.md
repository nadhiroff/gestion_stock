# FAI Gestion (Stock, Abonnements, Ventes, Facturation)

## Installation
1. Copier ce dossier dans `c:\\wamp64\\www\\Gestion_stock`.
2. Créer la base et les tables avec votre script SQL fourni (DB: `fai_gestion`).
3. Vérifier `includes/config.php` (URL, DB_USER, DB_PASS).
4. Ouvrir `http://rgz.bj/gestion_stock/public/` (zone publique).
5. Se connecter via `https://rgz.bj/gestion_stock/app/auth/login.php`.
   - Admin seed: email `admin@gmail.com`, mot de passe `Admin` (MD5 supporté).

## Tech
- PHP 8+, PDO MySQL, Bootstrap 5, Bootstrap Icons.
- Auth par session, pas d'inscription publique.

## À faire (roadmap)
- CRUD: produits, clients, fournisseurs, abonnements, ventes, paiements.
- Mouvements de stock (entrées/sorties) avec traçabilité utilisateur.
- Génération PDF facture (TCPDF) + envoi email.
- Rapports/exports.
- Migration sécurisée des mots de passe vers `password_hash()`.
# gestion_stock
# gestion_stock
