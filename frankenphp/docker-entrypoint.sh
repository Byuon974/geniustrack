#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	###> dunglas/symfony-docker ###
	# Install the project the first time PHP is started
	# This block will remove itself after the installation
	if [ "$(cat composer.json)" = '{}' ]; then
		rm -Rf tmp/
		composer create-project "symfony/skeleton $SYMFONY_VERSION" tmp --stability="$STABILITY" --prefer-dist --no-progress --no-interaction --no-install

		cd tmp
		cp -Rp . ..
		cd -
		rm -Rf tmp/

		composer require "php:>=$PHP_VERSION"
		composer config --json extra.symfony.docker 'true'

		# Remove the project install block from this script and the compose.yaml
		sed -i '/^\t###> dunglas\/symfony-docker ###/,/^\t###< dunglas\/symfony-docker ###/d' frankenphp/docker-entrypoint.sh
		sed -i '/###> dunglas\/symfony-docker ###/,/###< dunglas\/symfony-docker ###/d' compose.yaml

		if grep -q ^DATABASE_URL= .env; then
			echo 'To finish the installation please press Ctrl+C to stop Docker Compose and run: docker compose up --build --wait'
			sleep infinity
		fi
	fi
	###< dunglas/symfony-docker ###

	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		# Création de la base si absente (idempotent).
		php bin/console doctrine:database:create --if-not-exists --no-interaction || true

		# Création/mise à jour du schéma directement depuis les entités. Le projet
		# suit un modèle « tabula rasa + seed » : on repart d'une base vierge et on
		# la reconstruit par le seed, plutôt que de faire évoluer un schéma existant.
		# schema:update est l'outil adapté à ce modèle (pas de migrations versionnées
		# à maintenir pour un schéma toujours recréé à neuf).
		php bin/console doctrine:schema:update --force --complete --no-interaction || true

		# Amorçage initial des données (idempotent : ne duplique rien).
		php bin/console app:init-vitrine --no-interaction 2>/dev/null || true
		php bin/console app:charger-donnees --no-interaction 2>/dev/null || true
		php bin/console app:charger-demo --no-interaction 2>/dev/null || true
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
