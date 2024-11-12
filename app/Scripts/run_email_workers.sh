LOCK_DIR="/tmp/email_sync_locks"

mkdir -p "${LOCK_DIR}"

EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, id as email_id FROM email_accounts;")

while IFS=$'\t' read -r user_id email_id; do
    LOCK_FILE="${LOCK_DIR}/email_sync_${email_id}.lock"

    if [ -e "${LOCK_FILE}" ]; then
        echo "Processo de sincronização já em execução para email_id=${email_id}. Ignorando..."
        continue
    fi

    touch "${LOCK_FILE}"

    echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
    ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1 &

    (
        wait $!
        rm -f "${LOCK_FILE}"
    ) &

done <<< "$EMAIL_LIST"
