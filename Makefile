.PHONY: install dev backend frontend migrate seed test lint deploy

install:
	cd backend && composer install
	cd frontend && npm install
	@test -f backend/config.php || (cp backend/config.example.php backend/config.php && echo "Created backend/config.php")

dev:
	@echo "API: http://localhost:8000/api/v1/health   App: http://localhost:5173"
	@$(MAKE) -j2 backend frontend

backend:
	cd backend && php -S 127.0.0.1:8000 -t public public/index.php

frontend:
	cd frontend && npm run dev

migrate:
	cd backend && php bin/migrate.php

seed:
	cd backend && php bin/seed.php

test:
	cd backend && (test -x vendor/bin/phpunit && vendor/bin/phpunit || phpunit)

lint:
	cd backend && find src bin public tests -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null && echo "PHP syntax OK"
	cd frontend && npm run lint && npm run typecheck

deploy:
	deploy/iitd/deploy.sh
