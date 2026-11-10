FROM php:8.2-apache

# SSL証明書とMySQL接続用PDO拡張モジュールをインストール
RUN apt-get update && apt-get install -y ca-certificates && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql

# Apacheのmod_rewriteを有効化
RUN a2enmod rewrite

# アプリのコードをWeb公開ディレクトリに配置
COPY . /var/www/html/

# ポート80を開放
EXPOSE 80
