# Raccourcis fins par-dessus le standard symfony-docker (dunglas).
# Ne remplace pas docker compose : l'abrège pour les gestes fréquents.
# `make` seul liste les cibles.
#
# Le port HTTPS est paramétrable (compose : ${HTTPS_PORT:-443}). Pour faire
# tourner une seconde instance sans conflit, lancez par exemple :
#   HTTPS_PORT=8443 HTTP_PORT=8080 make up

.DEFAULT_GOAL := help

# E-mail admin par défaut pour les cibles de compte (surchargeable : make activer EMAIL=...).
EMAIL ?= admin@cci.re

help: ## Liste les commandes
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS=":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Démarre l'environnement (build au besoin)
	docker compose up --wait

down: ## Arrête l'environnement
	docker compose down --remove-orphans

build: ## (Re)construit les images proprement
	docker compose build --pull --no-cache

sh: ## Shell dans le conteneur applicatif
	docker compose exec php sh

logs: ## Suit les logs de l'application
	docker compose logs -f php

test: ## Lance la suite de tests (SQLite en mémoire)
	docker compose exec -e APP_ENV=test php bin/phpunit

audit: ## Vérifie les dépendances contre les avis de sécurité (avant déploiement)
	docker compose exec php composer audit --locked --no-interaction

db: ## Crée/met à jour le schéma depuis les entités
	docker compose exec php bin/console doctrine:schema:update --force --complete

reseed: ## Recharge les données de démonstration (sans vider la base)
	docker compose exec php bin/console app:charger-demo

activer: ## Réactive un compte désactivé : make activer EMAIL=prof@cci.re
	docker compose exec php bin/console app:activer-compte $(EMAIL) --lever-sanctions

reset: ## Remise à zéro TOTALE (vide la base) : make reset CONFIRME=oui
	@if [ "$(CONFIRME)" != "oui" ]; then \
		echo "Action destructrice : toutes les données seront perdues."; \
		echo "Relancez avec : make reset CONFIRME=oui"; \
		exit 2; \
	fi
	docker compose down -v --remove-orphans
	docker compose up --wait
	docker compose exec php bin/console doctrine:schema:update --force --complete
	docker compose exec php bin/console app:create-admin $(EMAIL) 'GeniusLab974!'
	docker compose exec php bin/console app:init-vitrine
	docker compose exec php bin/console app:charger-demo
	@echo "Base réinitialisée. Connexion : https://localhost/admin/dashboard ($(EMAIL))"

.PHONY: help up down build sh logs test audit db reseed activer reset
