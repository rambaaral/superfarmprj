FROM wordpress:php8.3-apache@sha256:4abf7a450ee477dde967584f8174d7e03221d224c4971a0c38d84e7254426e64
RUN apt-get update && apt-get install -y --no-install-recommends clamav clamav-freshclam cron \
    && rm -rf /var/lib/apt/lists/*
COPY site/ /opt/hpc/site/
COPY private/ /opt/hpc/private/
COPY seed/ /opt/hpc/seed/
COPY docker/ /opt/hpc/docker/
RUN chmod +x /opt/hpc/docker/entrypoint.sh /opt/hpc/docker/scan-wrapper \
    && printf 'upload_max_filesize=12M\npost_max_size=32M\nmemory_limit=512M\nmax_execution_time=120\n' > /usr/local/etc/php/conf.d/hpc.ini \
    && sed -i '/^NotifyClamd /d' /etc/clamav/freshclam.conf \
    && a2enmod rewrite
ENTRYPOINT ["/opt/hpc/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
