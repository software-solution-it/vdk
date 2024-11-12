DATABASE_USER="root"
DATABASE_PASS="root"
DATABASE_NAME="mail"
PHP_BIN="/usr/bin/php"
WORKER_SCRIPT="/home/suporte/vdk/app/Worker/email_sync_worker.php"

EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, id as email_id FROM email_accounts;")

while IFS=$'\t' read -r user_id email_id; do
    LOCK_FILE="/tmp/email_sync_${email_id}.lock"

    {
        # Tenta adquirir o lock para o email_id
        flock -n 200 || {
            echo "Processo de sincronização já em execução para email_id=${email_id}. Ignorando..."
            continue
        }

        # Se não houver outro processo, inicia o worker
        echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
        ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1

    } 200>"${LOCK_FILE}" &

done <<< "$EMAIL_LIST"

# Espera todos os processos em segundo plano terminarem antes de encerrar o script
wait
