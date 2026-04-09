# Guide de test — API Todo (Symfony + Docker)

## Prérequis

- Docker Desktop installé et démarré
- PHP 8.2+ et Composer installés localement
- Git

---

## 0. Installation initiale

```bash
# Cloner le repo et se placer dedans
git clone <url-du-repo>
cd API-REST-Pipeline-DOCKER

# Créer le fichier d'environnement
cp .env.example .env
```

---

## Étape 1 — Vérification de la structure Symfony

```bash
# Installer les dépendances PHP
composer install

# Vérifier que toutes les routes sont enregistrées
php bin/console debug:router
```

Résultat attendu :
```
health       GET    /health
task_list    GET    /api/tasks
task_show    GET    /api/tasks/{id}
task_create  POST   /api/tasks
task_update  PUT    /api/tasks/{id}
task_delete  DELETE /api/tasks/{id}
```

```bash
# Vérifier que le container de dépendances Symfony est valide
php bin/console lint:container
```

Résultat attendu : `[OK] The container was linted successfully`

---

## Étape 2 — Dockerfile optimisé

```bash
# Builder l'image manuellement
docker build -t todo-api:local .

# Vérifier la taille (doit être < 100 MB)
docker images todo-api:local
```

Résultat attendu :
```
REPOSITORY     TAG     SIZE
todo-api       local   < 100MB
```

```bash
# Vérifier que le conteneur tourne en non-root
docker run --rm todo-api:local whoami
```

Résultat attendu : `appuser`

---

## Étape 3 — Docker Compose (4 services)

```bash
# Supprimer le cache local pour éviter les conflits de permissions
rm -rf var/cache var/log

# Démarrer tous les services
docker compose up -d --build

# Vérifier que les 4 services sont UP et healthy
docker compose ps
```

Résultat attendu :
```
NAME          SERVICE   STATUS          PORTS
...-api-1     api       running         9000/tcp
...-db-1      db        running (healthy)
...-nginx-1   nginx     running         0.0.0.0:80->80/tcp
...-redis-1   redis     running
```

```bash
# Attendre ~15 secondes que PostgreSQL soit prêt
sleep 15

# Jouer les migrations (obligatoire au premier démarrage)
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction

# Tester le health check
curl http://localhost/health
```

Résultat attendu :
```json
{
  "status": "healthy",
  "timestamp": "2026-...",
  "database": "connected",
  "cache": "connected"
}
```

### Tester les endpoints CRUD

```bash
# Créer une tâche
curl -X POST http://localhost/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"title": "Ma première tâche", "description": "Test Docker"}'
```

Résultat attendu : HTTP 201 + JSON de la tâche créée

```bash
# Lister toutes les tâches
curl http://localhost/api/tasks
```

Résultat attendu : HTTP 200 + tableau JSON

```bash
# Récupérer la tâche id=1
curl http://localhost/api/tasks/1
```

Résultat attendu : HTTP 200 + JSON de la tâche

```bash
# Modifier la tâche id=1
curl -X PUT http://localhost/api/tasks/1 \
  -H "Content-Type: application/json" \
  -d '{"status": "done"}'
```

Résultat attendu : HTTP 200 + JSON mis à jour

```bash
# Supprimer la tâche id=1
curl -X DELETE http://localhost/api/tasks/1
```

Résultat attendu : HTTP 204 (No Content)

```bash
# Tester une tâche inexistante
curl -i http://localhost/api/tasks/99999
```

Résultat attendu : HTTP 404

```bash
# Tester la validation (title manquant)
curl -i -X POST http://localhost/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"description": "Pas de titre"}'
```

Résultat attendu : HTTP 422

```bash
# Consulter les logs des services
docker compose logs api
docker compose logs db
docker compose logs nginx
docker compose logs redis
```

---

## Étape 4 — Sécurité de l'image

```bash
# 1. Vérifier utilisateur non-root
docker compose exec api whoami
```

Résultat attendu : `appuser`

```bash
# 2. Scanner les CVE critiques et hautes avec Trivy
docker run --rm -v //var/run/docker.sock:/var/run/docker.sock \
  aquasec/trivy:0.61.0 image --severity CRITICAL,HIGH \
  api-rest-pipeline-docker-api:latest
```

Résultat attendu : `Total: 0 (HIGH: 0, CRITICAL: 0)`

```bash
# 3. Scanner les secrets exposés dans l'image
docker run --rm -v //var/run/docker.sock:/var/run/docker.sock \
  aquasec/trivy:0.61.0 image --scanners secret \
  api-rest-pipeline-docker-api:latest
```

Résultat attendu : aucun secret détecté

```bash
# 4. Vérifier que .env est bien ignoré par git
git check-ignore -v .env
```

Résultat attendu : `.gitignore:2:/.env  .env`

```bash
# 5. Vérifier qu'aucune version :latest n'est utilisée en prod
grep "latest" docker-compose.prod.yml
```

Résultat attendu : aucune ligne

---

## Étape 5 — Pipeline CI/CD

```bash
# Lint syntaxe PHP sur tout le code source
find src tests -name "*.php" -print0 | xargs -0 php -l
```

Résultat attendu : `No syntax errors detected` sur chaque fichier

```bash
# Lint du container Symfony en environnement test
php bin/console lint:container --env=test
```

Résultat attendu : `[OK] The container was linted successfully`

```bash
# Lancer les tests unitaires
php bin/phpunit tests/Unit/ --no-coverage
```

Résultat attendu :
```
OK (4 tests, 14 assertions)
```

```bash
# Lancer les tests unitaires avec rapport de couverture
php bin/phpunit tests/Unit/ --coverage-text
```

```bash
# Simuler le job CI complet en local (lint + tests)
composer validate --strict && \
find src tests -name "*.php" -print0 | xargs -0 php -l && \
php bin/console lint:container --env=test && \
php bin/phpunit tests/Unit/ --no-coverage
```

Résultat attendu : toutes les étapes passent sans erreur

---

## Test global — tout en une seule commande

Lance ce bloc pour valider l'ensemble du projet d'un coup :

```bash
# Repartir de zéro
docker compose down -v --remove-orphans
rm -rf var/cache var/log

# Démarrer
cp .env.example .env
docker compose up -d --build

# Attendre que la DB soit prête
sleep 15

# Jouer les migrations (obligatoire au premier démarrage)
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction

# Health check
echo "=== /health ===" && \
curl -s http://localhost/health | python -m json.tool

# CRUD complet
echo "=== POST /api/tasks ===" && \
curl -s -X POST http://localhost/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","description":"Validation CI"}' | python -m json.tool

echo "=== GET /api/tasks ===" && \
curl -s http://localhost/api/tasks | python -m json.tool

# Sécurité
echo "=== whoami ===" && \
docker compose exec api whoami

# Tests unitaires
echo "=== PHPUnit ===" && \
php bin/phpunit tests/Unit/ --no-coverage
```

---


### Reconstruire depuis zéro

```bash
docker compose down -v --remove-orphans
rm -rf var/cache var/log
docker compose up -d --build
```
