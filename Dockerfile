FROM dunglas/frankenphp

# Instalar extensões PHP necessárias
RUN install-php-extensions mysqli pdo_mysql

# Copiar arquivos da aplicação
COPY . /app

# Definir diretório de trabalho
WORKDIR /app

# Copiar Caddyfile customizado
COPY Caddyfile /etc/frankenphp/Caddyfile

# Expor porta 80
EXPOSE 80

# Comando para iniciar o FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
