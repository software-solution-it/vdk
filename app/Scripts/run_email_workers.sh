LOCK_DIR="/tmp/email_sync_locks"
mkdir -p "${LOCK_DIR}"

DATABASE_USER="root" 
DATABASE_PASS="root" 
DATABASE_NAME="mail"

EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, id as email_id FROM email_accounts;")

while IFS=$'\t' read -r user_id email_id; do
    LOCK_FILE="${LOCK_DIR}/email_sync_${email_id}.lock"

    if [ -e "${LOCK_FILE}" ]; then
        echo "Processo de sincronização já em execução para email_id=${email_id}. Ignorando..."
        continue
    fi

    # Cria o lock file
    touch "${LOCK_FILE}"

    echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
    ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1 &

    # Salvar o PID do processo no lock file para controle
    echo $! > "${LOCK_FILE}"

    # Usar um subshell para esperar pelo término do processo e então remover o lock file
    (
        pid=$(<"${LOCK_FILE}")
        wait "${pid}"
        rm -f "${LOCK_FILE}"
    ) &

done <<< "$EMAIL_LIST"
