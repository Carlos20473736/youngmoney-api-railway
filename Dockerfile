FROM dunglas/frankenphp

# Instalar extensões PHP necessárias
RUN install-php-extensions mysqli pdo_mysql

# Argumento para invalidar cache - BUILD_DATE=2026-02-05-v3
ARG CACHEBUST=1

# Copiar arquivos da aplicação (após CACHEBUST para forçar rebuild)
COPY . /app

# Copiar configuração PHP customizada
COPY php.ini /usr/local/etc/php/conf.d/99-youngmoney.ini

# Definir diretório de trabalho
WORKDIR /app

# Copiar Caddyfile customizado
COPY Caddyfile /etc/frankenphp/Caddyfile

# Criar diretório de logs
RUN mkdir -p /var/log && touch /var/log/php_errors.log && chmod 666 /var/log/php_errors.log

# Expor porta 80
EXPOSE 80

# Comando para iniciar o FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
