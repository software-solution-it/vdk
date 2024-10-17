
apt-get update
apt-get install -y supervisor php-cli

mkdir -p /var/log/supervisor

cp /app/scripts/supervisord.conf /etc/supervisor/supervisord.conf

/usr/bin/supervisord -c /etc/supervisor/supervisord.conf
