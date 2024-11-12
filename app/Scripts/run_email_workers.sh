DATABASE_USER="root"
DATABASE_PASS="root"
DATABASE_NAME="mail"
PHP_BIN="/usr/bin/php"
WORKER_SCRIPT="/home/suporte/vdk/app/Worker/email_sync_worker.php"

declare -A active_workers
declare -A last_updated

while true; do
    EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, id as email_id, updated_at FROM email_accounts;")

    declare -A current_emails
    while IFS=$'\t' read -r user_id email_id updated_at; do
        current_emails[${email_id}]=1

        lock_file="/tmp/email_sync_${email_id}.lock"
        if [[ "${last_updated[${email_id}]}" != "${updated_at}" ]]; then
            last_updated[${email_id}]="${updated_at}"
            if [[ -n "${active_workers[${email_id}]}" ]]; then
                echo "Reiniciando worker para email_id=${email_id} devido a atualização detectada."
                kill "${active_workers[${email_id}]}" 2>/dev/null
                wait "${active_workers[${email_id}]}" 2>/dev/null
                unset active_workers[${email_id}]
                rm -f "${lock_file}"
            fi

            if [[ -z "${active_workers[${email_id}]}" || ! -e "/proc/${active_workers[${email_id}]}" ]]; then
                {
                    if flock -n 200; then
                        echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
                        while true; do
                            ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1
                            if [[ $? -ne 0 ]]; then
                                echo "Erro no worker para email_id=${email_id}. Tentando novamente em 5 segundos..."
                                sleep 5
                            else
                                break
                            fi
                        done
                        echo "Worker finalizado para user_id=${user_id} e email_id=${email_id}"
                    fi
                } 200>"${lock_file}" &
                active_workers[${email_id}]=$!
            fi
        fi
    done <<< "$EMAIL_LIST"

 for email_id in "${!active_workers[@]}"; do
        if [[ -z "${current_emails[${email_id}]}" ]]; then
            echo "Finalizando worker para email_id=${email_id} que foi deletado do banco."
            kill "${active_workers[${email_id}]}" 2>/dev/null
            wait "${active_workers[${email_id}]}" 2>/dev/null
            unset active_workers[${email_id}]
            rm -f "/tmp/email_sync_${email_id}.lock"
        fi
    done

    unset current_emails

    sleep 5
done
