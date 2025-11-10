FROM wordpress:latest

RUN apt-get update \
    && apt-get install -y --no-install-recommends less mariadb-client \
    && rm -rf /var/lib/apt/lists/*

RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

COPY init-wp.sh /usr/local/bin/init-wp.sh
RUN chmod +x /usr/local/bin/init-wp.sh

ENTRYPOINT ["/usr/local/bin/init-wp.sh"]
