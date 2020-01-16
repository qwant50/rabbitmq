FROM centos:7

ARG HOST_UID=1000

RUN yum install -y \
        https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm \
        https://rpms.remirepo.net/enterprise/remi-release-7.rpm \
        yum-utils

RUN yum-config-manager --enable remi-php73 && yum update -y

RUN yum install -y \
        php \
        php-fpm \
        php-opcache \
        php-gd \
        php-pecl-xdebug \
        php-mbstring \
        php-zip \
        php-bcmath \
        php-dom \
        unzip \
        zip \
        git \
        mc \
        htop \
        vim \
        cronie

WORKDIR /library

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer