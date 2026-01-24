############################################
# Production Image - 从基础镜像构建
# 使用预构建的基础镜像可以大幅提升构建速度
############################################

# 支持从外部基础镜像构建（通过构建参数指定）
# 默认使用本地构建的 base-image:latest
# 使用方式：
#   docker build --build-arg BASE_IMAGE=your-registry/base-image:latest -f Dockerfile .
ARG BASE_IMAGE=liushoukun/php:8.2-fpm-nginx
FROM ${BASE_IMAGE} AS base

############################################
# Development Image
############################################
FROM base AS development

# 传递用户 ID 和组 ID 作为构建参数
# 确保 www-data 用户与运行 Docker 的用户具有相同的 UID 和 GID
ARG USER_ID
ARG GROUP_ID

USER root
RUN docker-php-serversideup-set-id www-data $USER_ID:$GROUP_ID && \
    docker-php-serversideup-set-file-permissions --owner $USER_ID:$GROUP_ID --service nginx

USER www-data

############################################
# CI Image
############################################
FROM base AS ci

USER root
RUN echo "user = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf

############################################
# Production Image
############################################
FROM base AS deploy

USER www-data
WORKDIR /var/www/html

# 先复制本地包目录（packages），因为 composer 依赖它们
# 本地包变化频率相对较低，可以更好地利用缓存
COPY --chown=www-data:www-data packages ./packages

# 复制 composer 文件，利用 Docker 缓存层
# 只有当 composer.json 或 composer.lock 变化时才重新安装依赖
COPY --chown=www-data:www-data composer.json composer.lock ./

# 安装 Composer 依赖（利用缓存，如果 packages 和 composer 文件没变则跳过）
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 复制应用代码（放在 composer install 之后，因为代码变化更频繁）
COPY --chown=www-data:www-data . .

# 运行 Composer 脚本（如 package discovery）
RUN composer dump-autoload --optimize --classmap-authoritative
