FROM dunglas/frankenphp

# Instalar extensões PHP necessárias
RUN install-php-extensions mysqli pdo_mysql

# Copiar arquivos da aplicação
# Build timestamp: 2026-02-06-fix-502
COPY . /app

# Copiar configuração PHP customizada
COPY php.ini /usr/local/etc/php/conf.d/99-youngmoney.ini

# Definir diretório de trabalho
WORKDIR /app

# Copiar Caddyfile customizado
COPY Caddyfile /etc/frankenphp/Caddyfile

# Criar diretório de logs
RUN mkdir -p /var/log && touch /var/log/php_errors.log && chmod 666 /var/log/php_errors.log

# Desabilitar HTTPS automático (Railway gerencia TLS)
ENV SERVER_NAME=":${PORT:-80}"

# Expor porta (Railway usa PORT dinâmica)
EXPOSE 80

# Comando para iniciar o FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
