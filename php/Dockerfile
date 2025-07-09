FROM php:7.4.33-fpm

WORKDIR /data/www

# 更换阿里云镜像源
RUN sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list && \
    sed -i 's|security.debian.org|mirrors.aliyun.com/debian-security|g' /etc/apt/sources.list

# 安装依赖
RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
    autoconf g++ gcc make libc-dev pkg-config re2c \
    libzip-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    libxml2-dev libsqlite3-dev libcurl4-openssl-dev \
    libssl-dev unzip git curl libonig-dev \
    supervisor procps libnghttp2-dev libpcre3-dev \
    libbz2-dev libsnmp-dev libxslt1-dev \
    libldap2-dev libpq-dev libedit-dev \
    libwebp-dev libxpm-dev libvpx-dev \
    libicu-dev libsodium-dev libargon2-dev \
    libgmp-dev libpspell-dev librecode-dev \
    libtidy-dev libkrb5-dev \
    libssh2-1-dev \
    net-tools \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# 编译核心PHP
RUN docker-php-source extract && \
    cd /usr/src/php && \
    cp configure configure.orig && \
    ./buildconf --force && \
    ./configure \
        --enable-mysqlnd \
        --enable-pdo \
        --enable-mysqli \
        --enable-pdo-mysql \
        --enable-pdo-sqlite \
        --disable-intl \
        --disable-debug \
        --enable-fpm \
        --with-fpm-user=www-data \
        --with-fpm-group=www-data \
        --with-config-file-path=/usr/local/etc/php \
        --with-config-file-scan-dir=/usr/local/etc/php/conf.d \
        --enable-mbstring \
        --with-curl \
        --with-openssl \
        --with-zlib \
        --with-sodium=shared \
        --with-password-argon2 \
        --with-iconv \
        --with-libedit \
        --enable-phar \
        --enable-json \
        --enable-tokenizer \
        --enable-dom \
        --enable-simplexml \
        --enable-xmlreader \
        --enable-xmlwriter \
        --enable-fileinfo \
        --enable-filter \
        --enable-hash \
        --enable-session \
        --enable-ctype \
        --enable-posix \
        --enable-libxml \
        --enable-cli \
        --disable-cgi \
        --enable-phpdbg \
    && make -j$(nproc) \
    && make install \
    && docker-php-source delete

RUN mkdir -p /var/log/supervisor && \
      chown www-data:www-data /var/log/supervisor

# 安装PECL扩展（Redis）
RUN pecl install redis-5.3.7 && \
    docker-php-ext-enable redis

# 显式配置扩展目录
RUN echo "extension_dir = /usr/local/lib/php/extensions/no-debug-non-zts-20190902" \
    > /usr/local/etc/php/conf.d/ext-dir.ini

# 配置企业微信 SDK
COPY extension /data/docker/php/extension
COPY C_sdk /data/docker/php/extension/C_sdk

RUN chmod +r /data/docker/php/extension/C_sdk/libWeWorkFinanceSdk_C.so && \
    cp /data/docker/php/extension/C_sdk/libWeWorkFinanceSdk_C.so /usr/local/lib/ && \
    echo "/usr/local/lib" > /etc/ld.so.conf.d/local.conf && \
    ldconfig && \
    cd /data/docker/php/extension && \
    phpize && \
    CFLAGS="-g -O0" ./configure --with-php-config=$(which php-config) \
        --with-wxwork-finance-sdk=/data/docker/php/extension/C_sdk/ && \
    make && make install && \
    echo "extension=wxwork_finance_sdk.so" > /usr/local/etc/php/conf.d/30-wxwork_finance_sdk.ini

# 安装Swoole扩展
RUN pecl install swoole-4.8.12 && \
    docker-php-ext-enable swoole

# 安装gd
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        gd bcmath bz2 calendar exif ftp gettext \
        pdo_sqlite shmop snmp soap sockets sysvmsg \
        sysvsem sysvshm tidy xmlrpc xsl zip \
        pdo_mysql

# 验证扩展安装
RUN ls -l /usr/local/lib/php/extensions/no-debug-non-zts-20190902/ \
    | grep -E 'gd.so|bcmath.so|redis.so|mysqli.so|pdo_mysql.so|pdo.so|swoole.so|wxwork_finance_sdk.so' || exit 1

# 验证 mysqlnd 安装
RUN php -i | grep -E 'mysqlnd|mysqli|pdo_mysql' || exit 1

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer && \
    composer --version  # 验证安装

# 配置时区
RUN echo "date.timezone = Asia/Shanghai" > /usr/local/etc/php/conf.d/timezone.ini

# 清理工作
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/src/php*

# 暴露端口
EXPOSE 9501

# 健康检查
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -f http://localhost:9501/status || exit 1