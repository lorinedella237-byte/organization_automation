# ================================================================
#                 LARAVEL 9 COMMAND CHEAT SHEET
#                    PHP 8.0 COMPATIBLE
# ================================================================


# ================================================================
# 1. STARTING / RUNNING THE LARAVEL PROJECT
# ================================================================

# Start Laravel development server
# Default port is 8000
php artisan serve

# Start Laravel on a specific port
php artisan serve --port=8080

# Start Laravel on a specific host and port
php artisan serve --host=127.0.0.1 --port=8080

# Example:
# http://127.0.0.1:8080


# ================================================================
# 2. ARTISAN HELP / GENERAL INFORMATION
# ================================================================

# Display all available Artisan commands
php artisan list

# Display Laravel version
php artisan --version

# Display Laravel environment
php artisan env

# Get help for a specific command
php artisan help migrate

# Get help for creating a model
php artisan help make:model


# ================================================================
# 3. CACHE COMMANDS
# ================================================================

# Clear application cache
php artisan cache:clear

# Clear configuration cache
php artisan config:clear

# Cache configuration
php artisan config:cache

# Clear compiled Blade views
php artisan view:clear

# Clear route cache
php artisan route:clear

# Cache routes
php artisan route:cache

# Clear event cache
php artisan event:clear

# Cache events
php artisan event:cache

# Clear all Laravel optimization caches
# Very useful when Laravel is behaving strangely
php artisan optimize:clear

# Cache Laravel optimizations
php artisan optimize


# ================================================================
# 4. ROUTES
# ================================================================

# Display all registered routes
php artisan route:list

# Display routes with middleware information
php artisan route:list -v

# Clear route cache
php artisan route:clear

# Cache routes
php artisan route:cache


# ================================================================
# 5. CONTROLLERS
# ================================================================

# Create a basic controller
php artisan make:controller UserController

# Create a resource controller
php artisan make:controller UserController --resource

# Create a controller connected to a model
php artisan make:controller UserController --model=User

# Create resource controller with model
php artisan make:controller UserController --resource --model=User


# ================================================================
# 6. MODELS
# ================================================================

# Create a model
php artisan make:model User

# Create model + migration
php artisan make:model User -m

# Create model + factory
php artisan make:model User -f

# Create model + controller
php artisan make:model User -c

# Create model + migration + factory + controller
php artisan make:model User -mfc

# Create model + migration + factory + seeder + controller + policy
php artisan make:model User -a


# ================================================================
# 7. MIGRATIONS
# ================================================================

# Create a migration
php artisan make:migration create_products_table

# Run all pending migrations
php artisan migrate

# Show migration status
php artisan migrate:status

# Rollback the last migration batch
php artisan migrate:rollback

# Rollback one migration
php artisan migrate:rollback --step=1

# Rollback two migrations
php artisan migrate:rollback --step=2

# Reset all migrations
php artisan migrate:reset

# Delete all database tables and run migrations again
php artisan migrate:fresh

# Delete all tables, migrate and run seeders
php artisan migrate:fresh --seed

# Rollback all migrations and migrate again
php artisan migrate:refresh

# Refresh migrations and run seeders
php artisan migrate:refresh --seed


# ================================================================
# 8. DATABASE SEEDERS
# ================================================================

# Create a seeder
php artisan make:seeder UserSeeder

# Run all seeders
php artisan db:seed

# Run a specific seeder
php artisan db:seed --class=UserSeeder

# Fresh database + migrations + seeders
php artisan migrate:fresh --seed


# ================================================================
# 9. FACTORIES
# ================================================================

# Create a factory
php artisan make:factory UserFactory

# Create a factory for a specific model
php artisan make:factory UserFactory --model=User


# ================================================================
# 10. TINKER
# ================================================================

# Open Laravel Tinker
php artisan tinker

# Inside Tinker you can test Laravel code, for example:
#
# User::count();
# User::all();
# User::find(1);
#
# Exit Tinker with:
# exit


# ================================================================
# 11. MIDDLEWARE
# ================================================================

# Create middleware
php artisan make:middleware CheckUser

# Example:
# php artisan make:middleware CheckAdmin


# ================================================================
# 12. FORM REQUESTS / VALIDATION
# ================================================================

# Create a form request
php artisan make:request StoreUserRequest

# Another example
php artisan make:request UpdateUserRequest


# ================================================================
# 13. POLICIES / AUTHORIZATION
# ================================================================

# Create a policy
php artisan make:policy UserPolicy

# Create policy for a model
php artisan make:policy UserPolicy --model=User


# ================================================================
# 14. JOBS / QUEUES
# ================================================================

# Create a job
php artisan make:job SendEmail

# Start queue worker
php artisan queue:work

# Process one queued job and stop
php artisan queue:work --once

# Restart queue workers
php artisan queue:restart


# ================================================================
# 15. EVENTS
# ================================================================

# Create an event
php artisan make:event UserRegistered

# Create a listener
php artisan make:listener SendWelcomeEmail


# ================================================================
# 16. NOTIFICATIONS
# ================================================================

# Create a notification
php artisan make:notification WelcomeNotification


# ================================================================
# 17. MAIL
# ================================================================

# Create a mail class
php artisan make:mail WelcomeEmail


# ================================================================
# 18. STORAGE
# ================================================================

# Create symbolic link from public/storage
# to storage/app/public
php artisan storage:link


# ================================================================
# 19. MAINTENANCE MODE
# ================================================================

# Put application into maintenance mode
php artisan down

# Bring application back online
php artisan up


# ================================================================
# 20. DATABASE
# ================================================================

# Show database information
php artisan db:show

# Show database table information
php artisan db:table users


# ================================================================
# 21. PUBLISHING
# ================================================================

# Publish package assets/configuration
php artisan vendor:publish


# ================================================================
# 22. SCHEDULE / TASKS
# ================================================================

# Run scheduled tasks once
php artisan schedule:run

# Run scheduler continuously during development
php artisan schedule:work


# ================================================================
# 23. CLEARING EVERYTHING
# ================================================================

# Clear application cache
php artisan cache:clear

# Clear configuration cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear compiled views
php artisan view:clear

# Clear event cache
php artisan event:clear

# Clear all optimization caches at once
php artisan optimize:clear


# ================================================================
# 24. COMMON DEVELOPMENT WORKFLOW
# ================================================================

# 1. Enter your Laravel project
cd my-app

# 2. Install PHP dependencies
composer install

# 3. Copy environment file if necessary
# Windows:
copy .env.example .env

# Linux / macOS:
cp .env.example .env

# 4. Generate Laravel application key
php artisan key:generate

# 5. Configure your database in .env

# 6. Run migrations
php artisan migrate

# 7. Start Laravel
php artisan serve


# ================================================================
# 25. WHEN YOU CHANGE .ENV
# ================================================================

# Clear cached configuration
php artisan config:clear

# Or clear all Laravel caches
php artisan optimize:clear


# ================================================================
# 26. WHEN ROUTES ARE NOT UPDATING
# ================================================================

# Clear route cache
php artisan route:clear

# Check your routes
php artisan route:list


# ================================================================
# 27. WHEN BLADE VIEWS ARE NOT UPDATING
# ================================================================

# Clear compiled Blade views
php artisan view:clear


# ================================================================
# 28. WHEN LARAVEL IS ACTING STRANGE
# ================================================================

# Clear all common Laravel caches
php artisan optimize:clear

# Then restart the server
php artisan serve


# ================================================================
# 29. CREATING A COMPLETE CRUD
# ================================================================

# Create model + migration + factory + controller
php artisan make:model Product -mfc

# Create resource controller instead
php artisan make:model Product -mf

php artisan make:controller ProductController --resource --model=Product

# Run migration
php artisan migrate

# Check routes
php artisan route:list


# ================================================================
# 30. CREATING A COMPLETE MODEL WITH EVERYTHING
# ================================================================

# - Model
# - Migration
# - Factory
# - Seeder
# - Controller
# - Policy

php artisan make:model Product -a

# Then run migrations
php artisan migrate

# Run seeders
php artisan db:seed


# ================================================================
# 31. COMMON ARTISAN MAKE COMMANDS
# ================================================================

php artisan make:model Product
php artisan make:controller ProductController
php artisan make:migration create_products_table
php artisan make:seeder ProductSeeder
php artisan make:factory ProductFactory
php artisan make:middleware CheckAdmin
php artisan make:request StoreProductRequest
php artisan make:policy ProductPolicy
php artisan make:job ProcessOrder
php artisan make:event OrderCreated
php artisan make:listener SendOrderEmail
php artisan make:mail OrderConfirmation
php artisan make:notification OrderNotification


# ================================================================
# 32. NPM / FRONTEND COMMANDS
# ================================================================

# Install JavaScript dependencies
npm install

# Run development frontend build
npm run dev

# Build frontend assets for production
npm run build


# ================================================================
# 33. COMPOSER COMMANDS
# ================================================================

# Install PHP dependencies
composer install

# Update PHP dependencies
composer update

# Add a package
composer require package/name

# Add development-only package
composer require package/name --dev

# Remove a package
composer remove package/name

# Show installed packages
composer show

# Dump Composer autoload files
composer dump-autoload


# ================================================================
# 34. LARAVEL PROJECT CREATION
# ================================================================

# Create Laravel 9 project compatible with PHP 8.0
composer create-project laravel/laravel:^9.0 my-app

# Enter project
cd my-app

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start Laravel
php artisan serve


# ================================================================
# 35. USEFUL COMMANDS TO MEMORIZE FIRST
# ================================================================

php artisan serve

php artisan route:list

php artisan make:model Product -m

php artisan make:controller ProductController --resource

php artisan migrate

php artisan migrate:fresh --seed

php artisan db:seed

php artisan tinker

php artisan optimize:clear

php artisan storage:link

php artisan list

php artisan --version


# ================================================================
#                    END OF CHEAT SHEET
# ================================================================
