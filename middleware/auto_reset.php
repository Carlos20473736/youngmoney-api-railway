<?php
/**
 * Auto Reset Middleware - DESATIVADO
 * 
 * IMPORTANTE: Este middleware foi DESATIVADO porque estava causando
 * resets indevidos do ranking em horários aleatórios.
 * 
 * O reset do ranking agora é feito APENAS pelo cron:
 * /api/v1/cron/auto_reset.php às 00:00 (meia-noite)
 * 
 * Esta função agora NÃO FAZ NADA - apenas retorna imediatamente.
 * Isso evita que o ranking seja resetado fora do horário programado.
 */

/**
 * Função mantida para compatibilidade, mas NÃO executa mais o reset
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @return void
 */
function checkAndResetRanking($conn) {
    // DESATIVADO - O reset agora é feito apenas pelo cron às 00:00 (meia-noite)
    // Esta função não faz mais nada para evitar resets indevidos
    return;
}
?>
