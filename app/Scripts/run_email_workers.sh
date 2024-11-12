DATABASE_USER="root"
DATABASE_PASS="root"
DATABASE_NAME="mail"
PHP_BIN="/usr/bin/php"
WORKER_SCRIPT="/home/suporte/vdk/app/Worker/email_sync_worker.php"

declare -A active_workers

while true; do
    # Obter a lista atualizada de emails ativos do banco
    EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, id as email_id FROM email_accounts;")

    # Armazenar os IDs dos emails em um array temporário para facilitar a verificação
    declare -A current_emails
    while IFS=$'\t' read -r user_id email_id; do
        current_emails[${email_id}]=1

        lock_file="/tmp/email_sync_${email_id}.lock"
        
        # Verificar se o worker já está ativo para o email_id
        if [[ -z "${active_workers[${email_id}]}" || ! -e "/proc/${active_workers[${email_id}]}" ]]; then
            {
                while true; do
                    if flock -n 200; then
                        echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
                        ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1
                        
                        echo "Worker finalizado para user_id=${user_id} e email_id=${email_id}. Reiniciando..."
                    else
                        echo "Worker já em execução para email_id=${email_id}. Aguardando para reiniciar..."
                        sleep 5
                    fi
                done
            } 200>"${lock_file}" &
            active_workers[${email_id}]=$!
        fi
    done <<< "$EMAIL_LIST"

    # Finalizar workers para emails que foram deletados
    for email_id in "${!active_workers[@]}"; do
        if [[ -z "${current_emails[${email_id}]}" ]]; then
            echo "Finalizando worker para email_id=${email_id} que foi deletado do banco."
            kill "${active_workers[${email_id}]}" 2>/dev/null
            unset active_workers[${email_id}]
            rm -f "/tmp/email_sync_${email_id}.lock"
        fi
    done

    # Limpar o array temporário de emails atuais
    unset current_emails

    # Aguardar alguns segundos antes de verificar novamente
    sleep 15
done
