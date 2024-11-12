DATABASE_USER="root"
DATABASE_PASS="root"
DATABASE_NAME="mail"
PHP_BIN="/usr/bin/php"
WORKER_SCRIPT="/home/suporte/vdk/app/Worker/email_sync_worker.php"

declare -A active_workers

sync_email_in_loop() {
    local user_id=$1
    local email_id=$2
    local lock_file="/tmp/email_sync_${email_id}.lock"

    while true; do
        {
            if flock -n 200; then
                echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
                ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1
                
                echo "Worker finalizado para user_id=${user_id} e email_id=${email_id}. Reiniciando..."
            else
                echo "Worker já em execução para email_id=${email_id}. Aguardando para reiniciar..."
                sleep 5
            fi
        } 200>"${lock_file}"
    done
}

while true; do
    EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, id as email_id FROM email_accounts;")

    while IFS=$'\t' read -r user_id email_id; do
        if [[ -z "${active_workers[${email_id}]}" || ! -e "/proc/${active_workers[${email_id}]}" ]]; then
            sync_email_in_loop "${user_id}" "${email_id}" &
            active_workers[${email_id}]=$!
        fi
    done <<< "$EMAIL_LIST"

    sleep 15
done
