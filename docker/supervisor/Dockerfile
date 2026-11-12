ARG  HTTPD_VERSION=0.1

FROM shopexhub/httpd2:supervisor-php82-alpine3.24-v1.0

ENV APP_DIR=/data/httpd/oms

WORKDIR $APP_DIR
COPY . $APP_DIR

RUN mkdir /etc/supervisor.d\
    && cp -f /data/httpd/oms/docker/supervisor/conf/* /etc/supervisor.d/

COPY ./docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN rm -rf ./docker .git .gitlab-ci.yml .gitignore /var/www/html/phpinfo.php

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 安装 composer（需仓库内提交 composer.lock；镜像构建不 self-update，避免版本漂移）
RUN mv ./tools/composer.phar /usr/local/bin/composer; \
    composer config -g repos.packagist composer https://mirrors.aliyun.com/composer/; \
    composer install --no-dev --no-interaction --prefer-dist || ( \
        composer config -g --unset repos.packagist; \
        composer install --no-dev --no-interaction --prefer-dist \
    );

# 配置文件
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh", "supervisord", "-c", "/etc/supervisord.conf", "-n"]
