PHP_BIN="/usr/bin/php"
PROJECT_DIR="/home/suporte/vdk"
WORKER_SCRIPT="${PROJECT_DIR}/app/Worker/email_sync_worker.php"

DATABASE_USER="root" 
DATABASE_PASS="root" 
DATABASE_NAME="mail"

EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, email_id FROM email_accounts;")

while IFS=$'\t' read -r user_id email_id; do
    echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
    ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1 &
done <<< "$EMAIL_LIST"
