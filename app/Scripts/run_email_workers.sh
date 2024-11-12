DATABASE_USER="root" 
DATABASE_PASS="root" 
DATABASE_NAME="mail"


EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, id as email_id FROM email_accounts;")

while IFS=$'\t' read -r user_id email_id; do
    LOCK_FILE="/tmp/email_sync_${email_id}.lock"

    # Usar flock para garantir que apenas um processo por email_id seja executado ao mesmo tempo
    {
        flock -n 200 || {
            echo "Processo de sincronização já em execução para email_id=${email_id}. Ignorando..."
            continue
        }

        echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
        ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1

    } 200>"${LOCK_FILE}" &

done <<< "$EMAIL_LIST"

wait
