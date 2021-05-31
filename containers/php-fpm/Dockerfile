ARG PHP_VERSION

FROM php:${PHP_VERSION}-fpm

LABEL maintainer="Davide Porrovecchio <davide.porrovecchio@agid.gov.it>"

ENV DEBIAN_FRONTEND noninteractive

# php required extensions
ARG PACKAGES="libfaketime procps libjpeg62-turbo libpng16-16 libfreetype6 unzip"
ARG BUILD_PACKAGES="autoconf build-essential wget libicu-dev libpng-dev libfreetype6-dev libjpeg62-turbo-dev libzip-dev"
ARG MATOMO_PHPREDIS_VERSION
RUN apt-get update && apt-get install -y --no-install-recommends ${BUILD_PACKAGES} ${PACKAGES} && \
    docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ && \
    docker-php-ext-install pdo_mysql gd opcache zip bcmath pcntl intl

# phpredis
RUN if [ -n "${MATOMO_PHPREDIS_VERSION}" ]; then \
    pecl channel-update pecl.php.net && \
    yes no | pecl install redis-${MATOMO_PHPREDIS_VERSION} && \
    docker-php-ext-enable redis \
;fi

# xdebug
ARG INSTALL_XDEBUG=false
RUN if [ ${INSTALL_XDEBUG} = true ]; then \
    pecl channel-update pecl.php.net && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug \
;fi

# xdebug conf
COPY ./xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
    
# install phpredis
RUN pecl install redis \
    && echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini

RUN apt-get autoremove --purge -y ${BUILD_PACKAGES} && \
    rm -rf /var/lib/apt/lists/*

# dusk deps
ENV PATH "/usr/local/node/bin:$PATH"
ARG ENV=local
ARG CHROME_BROWSER_CHANNEL=stable
RUN if [ "env-${ENV}" = "env-local" ] || [ "env-${ENV}" = "env-testing" ]; then \
    apt-get update \
    && apt-get -y --no-install-recommends install apt-utils wget unzip libnss3 libgconf-2-4 xdg-utils hicolor-icon-theme gnupg \
    && wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | apt-key add - \
    && chrome_version=`wget -qO- http://omahaproxy.appspot.com/history | awk -v CHANNEL="$CHROME_BROWSER_CHANNEL" -F [,\.] '$1 == "linux" && $2 == CHANNEL {print $3"."$4"."$5}' | head -1` \
    && wget https://dl.google.com/linux/direct/google-chrome-${CHROME_BROWSER_CHANNEL}_current_amd64.deb \
    && dpkg -i --force-depends google-chrome-${CHROME_BROWSER_CHANNEL}_current_amd64.deb \
    && apt-get -y -f --no-install-recommends install \
    && dpkg -i --force-depends google-chrome-${CHROME_BROWSER_CHANNEL}_current_amd64.deb \
    && rm google-chrome-${CHROME_BROWSER_CHANNEL}_current_amd64.deb \
    && chrome_driver_version=`wget -qO- http://chromedriver.storage.googleapis.com/LATEST_RELEASE_"$chrome_version"` \
    && wget https://chromedriver.storage.googleapis.com/"$chrome_driver_version"/chromedriver_linux64.zip \
    && unzip -q chromedriver_linux64.zip \
    && mv chromedriver /usr/local/bin/ \
    && rm chromedriver_linux64.zip \
    && wget -O node-build.zip https://github.com/nodenv/node-build/archive/master.zip \
    && unzip node-build.zip \
    && node-build-master/install.sh \
    && rm -Rf node-build-master node-build.zip \
;fi

# configuration files
COPY ./php.ini /usr/local/etc/php/php.ini

# cleanup
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

ARG APP_CODE_PATH_CONTAINER
WORKDIR ${APP_CODE_PATH_CONTAINER}

ENV LD_PRELOAD /usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1
ENV FAKETIME_NO_CACHE 1

CMD ["php-fpm"]

EXPOSE 9000
