prestashopCallbackModule
========================

Sends a POST request to an URL after an order (hook _actionValidateOrder_) with a JSON payload (id_order, id_customer, email, customized_data, total_paid, ... ).
Prestashop Module


Requirements
====

- [JavaScript Object Notation (JSON)](php.net/manual/book.json.php)
- [Client URL Library (curl)](php.net/manual/book.curl.php)
- Tested against Prestashop 1.6.*


FR
===

Module pour Prestashop (e-commerce).

Envoie une requête (POST) vers une URL après une commande.
Des informations sur la commande sont transmis sous forme de chaine JSON dans le corps de la requête.


Permet de déclancher des actions dans un système externe (fabrication d'un produit, mise à jour d'une base de données).
Evite ainsi de devoir faire des synchronisations périodiques avec des scripts.