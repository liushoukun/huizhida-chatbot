############################################
# Base Image - 基础镜像
# 包含 PHP 扩展和基础配置，这些内容很少变化
# 可以单独构建并推送到镜像仓库，避免每次重复构建
############################################

FROM serversideup/php:8.2-fpm-nginx

# 切换到 root 用户安装 PHP 扩展
USER root

# 安装 PHP 扩展（去重并优化）
# 注意：原 Dockerfile 中有重复的 bcmath 和 gd，已去重
RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    mbstring \
    pcntl \
    redis \
    igbinary \
    msgpack \
    exif \
    opcache

# 设置环境变量
ENV AUTORUN_LARAVEL_STORAGE_LINK=true \
    PHP_OPCACHE_ENABLE=true

# 设置工作目录
WORKDIR /var/www/html

# 切换回 www-data 用户
USER www-data
