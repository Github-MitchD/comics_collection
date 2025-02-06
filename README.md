# Comics Collection

![PHP](https://img.shields.io/badge/php-%3E%3D8.2-blue)
![Composer](https://img.shields.io/badge/composer-%3E%3D2.8-red)
![Symfony](https://img.shields.io/badge/symfony-%3E%3D7.2-green)

## Description
Comics Collection est une application web conçue pour gérer une collection de comics et d'auteurs. Elle permet d'ajouter, de modifier et de supprimer des comics et des auteurs, ainsi que de visualiser des statistiques sur la collection. Cette application consomme une API RESTful pour alimenter et synchroniser les données avec une base de données externe. Ce projet est conçu comme une démonstration et est disponible dans mon portfolio.

## Prérequis
- PHP 7.4 ou supérieur
- Composer
- Symfony CLI

## Installation

1. Clonez le dépôt :
    ```sh
    git clone https://github.com/Github-MitchD/comics_collection.git
    cd comics_collection
    ```

2. Installez les dépendances PHP :
    ```sh
    composer install
    ```

3. Configurez les variables d'environnement :
    Copiez le fichier [.env](http://_vscodecontentref_/0) et renommez-le en `.env.local`, puis modifiez les valeurs selon votre configuration.

## Utilisation

1. Démarrez le serveur de développement Symfony :
    ```sh
    symfony server:start
    ```

2. Accédez à l'application dans votre navigateur à l'adresse `http://localhost:8000`.

## Fonctionnalités de la version actuelle MVP
- Ajouter, modifier et supprimer des comics (admin)
- Ajouter, modifier et supprimer des auteurs (admin)
- Visualiser la liste de comics par collection, par auteur, par nouveauté
- Visualiser la liste des auteurs incluant le nombre de comics
- Visualiser les détails d'un comic incluant l'auteur
- Visualiser les détails d'un auteur incluant la liste des comics

## Contribuer
Les contributions sont les bienvenues ! Veuillez soumettre une pull request ou ouvrir une issue pour discuter des changements que vous souhaitez apporter.

## Licence
Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus d'informations.

## Contact
Michel Dufour - [PORTFOLIO](https://micheldufour.fr/) - [LinkedIn](https://www.linkedin.com/in/michel-dufour-b7570b187/)

Lien du projet: [API Comics Collection](https://comics-collection.micheldufour.fr/)