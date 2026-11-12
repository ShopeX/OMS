ARG  HTTPD_VERSION=0.1

FROM shopexhub/httpd2:oms-8.2.35-apache-alpine3.24-v1.0

ENV APP_DIR=/data/httpd/oms
RUN set -eux; \
	mkdir -p "$APP_DIR"; \
	chown -R apache:apache "$APP_DIR";

WORKDIR $APP_DIR

COPY . $APP_DIR

# 给目录权限
RUN chown -R apache:apache ./config ./data ./public; \
	ln -sf /dev/stderr  /var/log/apache2/error.log; \
	echo 'session.upload_progress.enabled = 0' >> /usr/local/etc/php/php.ini; \
	echo 'session.use_strict_mode = 1' >> /usr/local/etc/php/php.ini; 


COPY ./docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY ./app/base/examples/config.php $APP_DIR/config/config.php
COPY ./app/taskmgr/config/config.sample.php $APP_DIR/app/taskmgr/config/config.php


RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 安装 composer（需仓库内提交 composer.lock；镜像构建不 self-update，避免版本漂移）
RUN mv ./tools/composer.phar /usr/local/bin/composer; \
    composer config -g repos.packagist composer https://mirrors.aliyun.com/composer/; \
    composer install --no-dev --no-interaction --prefer-dist || ( \
        composer config -g --unset repos.packagist; \
        composer install --no-dev --no-interaction --prefer-dist \
    );

RUN rm -rf ./docker/docker-entrypoint.sh ./docker/Dockerfile .git .gitlab-ci.yml .gitignore /var/www/html/phpinfo.php ./composer.json ./composer.lock ./tools/composer.phar

# 配置文件
ENTRYPOINT ["docker-entrypoint.sh", "apache2-foreground"]
#CMD ["apache2-foreground"]
