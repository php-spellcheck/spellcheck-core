DOCKER ?= docker compose run -v $(PWD):/app -w /app --rm php
PHP    ?= php

.DEFAULT_GOAL := help
.PHONY: help build install test test-integration test-all stan cs cs-fix smoke shell validate release clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

build: ## Build the development image
	docker compose build

install: ## Install the dependencies
	$(DOCKER) composer install --no-interaction

test: ## Unit and functional tests (no external binary needed)
	$(DOCKER) vendor/bin/phpunit

test-integration: ## Tests against the real hunspell and aspell binaries
	$(DOCKER) vendor/bin/phpunit --group integration

test-all: test test-integration ## Everything

stan: ## Static analysis
	$(DOCKER) vendor/bin/phpstan analyse --memory-limit=-1

cs: ## Check the coding standard
	$(DOCKER) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix the coding standard
	$(DOCKER) vendor/bin/php-cs-fixer fix

smoke: ## Dependency free sanity check of the engine
	$(DOCKER) $(PHP) tests/smoke.php

validate: ## Validate composer.json
	$(DOCKER) composer validate --strict

release: ## Tag a version: make release VERSION=1.0.0
	@test -n "$(VERSION)" || (echo "VERSION is required, e.g. make release VERSION=1.0.0" && exit 1)
	@git diff --quiet || (echo "The working tree is dirty" && exit 1)
	git tag -a v$(VERSION) -m "Release $(VERSION)"
	git push origin v$(VERSION)

shell: ## Open a shell in the container
	$(DOCKER) bash

clean: ## Remove caches and generated files
	rm -rf vendor .phpunit.cache .php-cs-fixer.cache build
