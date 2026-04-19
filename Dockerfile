FROM php:8.2-apache

RUN set -eux; \
    groupmod -o -g 1000 www-data; \
    usermod -o -u 1000 -g 1000 www-data

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

RUN { \
	echo 'upload_max_filesize=12M'; \
	echo 'post_max_size=12M'; \
	echo 'max_file_uploads=20'; \
} > /usr/local/etc/php/conf.d/uploads.ini
