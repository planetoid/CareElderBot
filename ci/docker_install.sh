#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

# Install git (the php image doesn't have it) which is required by composer
apt-get update -yqq
apt-get install git -yqq

# Install GD
apt-get install -y libpng-dev
docker-php-ext-install gd

# fix the issue: linecorp/line-bot-sdk 3.6.1 requires ext-sockets
docker-php-ext-install sockets
# apt-get install php7.0-curl


docker-php-ext-install mbstring



apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-configure zip --with-libzip \
  && docker-php-ext-install zip

# Install composer
# curl --silent --show-error https://getcomposer.org/installer | php
# Install Composer and make it available in the PATH
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Install dependencies with Composer.
# --prefer-source fixes issues with download limits on GitHub.
# --no-interaction makes sure composer can run fully automated
composer install --prefer-source --no-interaction

composer update


# Install phpunit, the tool that we will use for testing
curl --location --output /usr/local/bin/phpunit https://phar.phpunit.de/phpunit.phar
chmod +x /usr/local/bin/phpunit

# Install PHP_CodeSniffer
curl --location --output /usr/local/bin/phpcs https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar
chmod +x /usr/local/bin/phpcs


# Install mysql driver
# Here you can install any other extension that you need
# docker-php-ext-install pdo_mysql

# fix Error No code coverage driver is available
pecl install xdebug && docker-php-ext-enable xdebug