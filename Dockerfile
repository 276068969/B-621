FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# 替换 APT 源为阿里云镜像加速构建
RUN sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update && apt-get install -y \
       libfreetype6-dev \
       libjpeg62-turbo-dev \
       libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/lite-entrypoint
COPY . /var/www/html

RUN a2enmod rewrite \
  && chown -R www-data:www-data /var/www/html

RUN chmod +x /usr/local/bin/lite-entrypoint

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/lite-entrypoint"]
