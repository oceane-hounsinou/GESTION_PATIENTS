# Projet 2 : Gestion des Patients d'une Clinique
<img width="1672" height="941" alt="patient" src="https://github.com/user-attachments/assets/ae449f43-68ca-4cea-b1e5-a6dbee7c5c18" />

## Description
Application PHP/MySQL (CRUD) pour l'enregistrement et le suivi des patients dans une clinique située à Cotonou.

## Fonctionnalités
- **Ajout, modification, listing et suppression** de fiches patients.
- **Gestion des rendez-vous patients** avec planification, modification, suppression et suivi des statuts.
- Interface basee sur **Bootstrap local** charge depuis `assets/bootstrap`.
- Création automatique de la base de données `gestion_clinique`.

## Règles Métier / Validations
- **Numéro de téléphone** : Le contact doit obligatoirement commencer par `229` suivi exactement de 8 chiffres.
- **Sexe** : Un bouton radio (Homme/Femme) doit obligatoirement être sélectionné avant la soumission du formulaire, sans quoi l'envoi est bloqué.
- Validations croisées (JavaScript en front-end et PHP en back-end).
