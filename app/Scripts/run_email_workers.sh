DATABASE_USER="root"
DATABASE_PASS="root"
DATABASE_NAME="mail"
PHP_BIN="/usr/bin/php"
WORKER_SCRIPT="/home/suporte/vdk/app/Worker/email_sync_worker.php"

# Função que sincroniza o e-mail em loop contínuo
sync_email_in_loop() {
    local user_id=$1
    local email_id=$2
    local lock_file="/tmp/email_sync_${email_id}.lock"

    while true; do
        {
            # Tenta adquirir o lock para o email_id
            if flock -n 200; then
                echo "Iniciando worker para user_id=${user_id} e email_id=${email_id}"
                ${PHP_BIN} ${WORKER_SCRIPT} ${user_id} ${email_id} > /dev/null 2>&1
                
                # Quando o processo terminar, o lock será automaticamente liberado pelo `flock`
                echo "Worker finalizado para user_id=${user_id} e email_id=${email_id}. Reiniciando..."
            else
                # Se o lock já estiver sendo usado, aguarda antes de tentar novamente
                echo "Worker já em execução para email_id=${email_id}. Aguardando para reiniciar..."
                sleep 5
            fi
        } 200>"${lock_file}"
    done
}

# Obter a lista de emails do banco de dados
EMAIL_LIST=$(mysql -u ${DATABASE_USER} -p${DATABASE_PASS} -D ${DATABASE_NAME} -sse "SELECT user_id, id as email_id FROM email_accounts;")

# Iterar sobre cada email para iniciar o processo de sincronização em loop
while IFS=$'\t' read -r user_id email_id; do
    sync_email_in_loop "${user_id}" "${email_id}" &
done <<< "$EMAIL_LIST"

# Espera todos os processos em segundo plano terminarem antes de encerrar o script
wait
