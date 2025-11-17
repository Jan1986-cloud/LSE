# Railway WordPress - Marketing Site

FROM wordpress:6.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    less \
    mysql-client \
    curl \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Copy custom configuration
COPY wp-config-railway.php /var/www/html/wp-config.php
COPY init.sh /var/www/html/init.sh
RUN chmod +x /var/www/html/init.sh

# Configure Apache for Railway
RUN a2enmod rewrite
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Railway uses PORT env variable
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2

# Expose port (Railway assigns dynamically)
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=60s \
  CMD curl -f http://localhost:8080/wp-admin/install.php || exit 1

# Start script
CMD ["/var/www/html/init.sh"]
