// ============================================
// GRANINHA BOT - VERSÃO OTIMIZADA 100% LEGIT
// ============================================
// Simulação completa de comportamento humano
// com vídeos, timing variável e padrões naturais
// ============================================

// Configuração global
let config = {
    bearerToken: '',
    exId: '',
    secretKey: '0NtCe2obYa13c3Bc1UEbVVj4p8IEDW',
    baseUrl: 'https://painel.graninha.com.br/api/v1',
    autoLoop: true
};

// IDs dos 33 jogos válidos
const GAME_IDS = [
    1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
    11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
    21, 22, 23, 24, 25, 26, 27, 28, 29, 30,
    31, 32, 33
];

// Valores possíveis da ROLETA (baseado em get_spin)
const ROLETA_VALORES = [15, 18, 25, 30, 35, 60, 80, 100];

// Valores possíveis da RASPADINHA (observados nas capturas: 14, 15, 22)
// Estimativa baseada em padrão similar à roleta
const RASPADINHA_VALORES = [10, 12, 14, 15, 18, 20, 22, 25, 28, 30, 35, 40, 45, 50];

// QUIZ sempre ganha 25 pontos fixos (id: 0)
const QUIZ_VALOR_FIXO = 0; // Envia id: 0, servidor retorna 25 pontos

// Estado do bot
let botState = {
    running: false,
    saldoInicial: 0,
    saldoAtual: 0,
    ganhoTotal: 0,
    acoesExecutadas: 0,
    loopInterval: null
};

// Elementos DOM
const configForm = document.getElementById('configForm');
const configSection = document.getElementById('configSection');
const statusSection = document.getElementById('statusSection');
const logsContainer = document.getElementById('logsContainer');
const stopBtn = document.getElementById('stopBtn');
const clearLogsBtn = document.getElementById('clearLogsBtn');

// Event Listeners
configForm.addEventListener('submit', iniciarBot);
stopBtn.addEventListener('click', pararBot);
clearLogsBtn.addEventListener('click', limparLogs);

// ============================================
// FUNÇÕES DE UTILIDADE E COMPORTAMENTO HUMANO
// ============================================

/**
 * Gera número aleatório entre min e max (inclusivo)
 */
function randomRange(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Gera número aleatório com distribuição normal (mais natural)
 */
function randomNormal(min, max) {
    const u1 = Math.random();
    const u2 = Math.random();
    const z0 = Math.sqrt(-2.0 * Math.log(u1)) * Math.cos(2.0 * Math.PI * u2);
    const value = ((z0 + 3) / 6) * (max - min) + min;
    return Math.max(min, Math.min(max, Math.floor(value)));
}

/**
 * Adiciona variação humana ao tempo (±20%)
 */
function addHumanVariation(baseTime) {
    const variation = baseTime * 0.2; // ±20%
    return randomRange(baseTime - variation, baseTime + variation);
}

/**
 * Simula hesitação humana antes de clicar
 */
async function hesitacaoHumana() {
    const tempo = randomRange(800, 2500);
    addLog(`⏱️ Preparando ação... (${(tempo/1000).toFixed(1)}s)`, 'info');
    await sleep(tempo);
}

/**
 * Simula micro-pausa aleatória (30% de chance)
 */
async function microPause() {
    if (Math.random() < 0.3) {
        const tempo = randomRange(1500, 4500);
        addLog(`💭 Pausa natural... (${(tempo/1000).toFixed(1)}s)`, 'info');
        await sleep(tempo);
    }
}

/**
 * Decide se deve executar ação (85% de chance por padrão)
 */
function shouldDoAction(probability = 0.85) {
    return Math.random() < probability;
}

/**
 * FUNÇÃO CRÍTICA: Simula assistir vídeo de propaganda
 * Esta é a parte mais importante para evitar detecção!
 */
async function assistirVideo(tipoAcao = 'ação') {
    addLog(`📺 Carregando propaganda para ${tipoAcao}...`, 'info');
    
    // 1. Tempo de carregamento do vídeo (2-5s)
    const tempoCarregamento = randomRange(2000, 5000);
    await sleep(tempoCarregamento);
    
    // 2. Tempo de assistir vídeo (25-40s com variação humana)
    // Baseado na análise do vídeo real
    const videoDurationBase = randomRange(25000, 40000);
    const videoDuration = addHumanVariation(videoDurationBase);
    
    addLog(`📹 Assistindo propaganda... (${(videoDuration/1000).toFixed(1)}s)`, 'warning');
    
    // Simular "heartbeat" durante vídeo (a cada 8-12s)
    const heartbeatInterval = randomRange(8000, 12000);
    const heartbeats = Math.floor(videoDuration / heartbeatInterval);
    
    for (let i = 0; i < heartbeats; i++) {
        await sleep(heartbeatInterval);
        if (i < heartbeats - 1) {
            addLog(`📺 Assistindo... (${((i+1) * heartbeatInterval / 1000).toFixed(0)}s)`, 'info');
        }
    }
    
    // Tempo restante do vídeo
    const tempoRestante = videoDuration - (heartbeats * heartbeatInterval);
    if (tempoRestante > 0) {
        await sleep(tempoRestante);
    }
    
    // 3. Tempo para fechar vídeo e processar (1-3s)
    const tempoFechar = randomRange(1000, 3000);
    addLog(`✅ Propaganda concluída, fechando...`, 'success');
    await sleep(tempoFechar);
}

/**
 * Simula tempo para ver e processar resultado
 */
async function verResultado() {
    const tempo = randomRange(2000, 5000);
    await sleep(tempo);
}

/**
 * Intervalo natural entre ações
 */
async function intervaloEntreAcoes() {
    const tempo = randomNormal(3000, 10000);
    await sleep(tempo);
}

// ============================================
// FUNÇÕES DE LOG E INTERFACE
// ============================================

function addLog(message, type = 'info') {
    const time = new Date().toLocaleTimeString('pt-BR');
    const logEntry = document.createElement('div');
    logEntry.className = `log-entry ${type}`;
    logEntry.innerHTML = `
        <span class="log-time">${time}</span>
        <span class="log-message">${message}</span>
    `;
    logsContainer.appendChild(logEntry);
    logsContainer.scrollTop = logsContainer.scrollHeight;
}

function limparLogs() {
    logsContainer.innerHTML = '';
    addLog('Logs limpos', 'info');
}

function atualizarStats() {
    document.getElementById('saldoInicial').textContent = botState.saldoInicial;
    document.getElementById('saldoAtual').textContent = botState.saldoAtual;
    document.getElementById('ganhoTotal').textContent = `+${botState.ganhoTotal}`;
    document.getElementById('acoesExecutadas').textContent = botState.acoesExecutadas;
}

// ============================================
// FUNÇÕES DE CRIPTOGRAFIA E PAYLOAD
// ============================================

function loadMD5Library() {
    return new Promise((resolve, reject) => {
        if (typeof CryptoJS !== 'undefined') {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

function gerarHash(idParam, typeParam, xParam) {
    const data = `${config.exId}_${idParam}_${typeParam}_${xParam}${config.secretKey}`;
    const md5Hash = CryptoJS.MD5(data).toString();
    return btoa(md5Hash);
}

function criarPayload(idParam, ex, typeParam, xParam) {
    const payload = {
        id: idParam,
        ex_id: config.exId,
        ex: ex,
        type: typeParam,
        i4: String(Math.floor(Math.random() * 900)),
        x_: xParam,
        ts: Math.floor(Date.now() / 1000),
        dt_x_X: gerarHash(idParam, typeParam, xParam)
    };
    
    const jsonStr = JSON.stringify(payload);
    const layer1 = btoa(jsonStr);
    const layer2 = btoa(layer1);
    
    return `data=${layer2}`;
}

// ============================================
// FUNÇÕES DE API
// ============================================

async function fazerRequest(endpoint, data = null) {
    // Usar proxy CORS para permitir requisições do navegador
    const apiUrl = `${config.baseUrl}/${endpoint}`;
    const url = `https://corsproxy.io/?${encodeURIComponent(apiUrl)}`;
    
    const options = {
        method: data ? 'POST' : 'GET',
        headers: {
            'accept': 'application/json',
            'authorization': `Bearer ${config.bearerToken}`,
            'content-type': 'application/x-www-form-urlencoded',
            'user-agent': 'okhttp/4.12.0'
        }
    };
    
    if (data) {
        options.body = data;
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        result.http_code = response.status;
        return result;
    } catch (error) {
        return { error: error.message, code: 500 };
    }
}

async function verSaldo() {
    const payload = criarPayload(0, "", 15, 1);
    const result = await fazerRequest("user", payload);
    
    if (result.code === 1) {
        return result.balance || 0;
    }
    
    return -1;
}

async function verificarLimite(ex) {
    const idParam = ex === "1" ? 1 : (ex === "2" ? 2 : 3);
    const payload = criarPayload(idParam, ex, 4, 0);
    const result = await fazerRequest("datas", payload);
    
    if (result.code === 201) {
        const limit = parseInt(result.limit || 0);
        const count = parseInt(result.count || 0);
        
        const disponivel = count < 0 ? Math.abs(count) : Math.max(0, limit - count);
        return disponivel;
    }
    
    return 0;
}

// ============================================
// FUNÇÕES DE EXECUÇÃO DE AÇÕES (OTIMIZADAS)
// ============================================

async function executarAcao(ex, nomeAcao, xValue) {
    // Escolher valor baseado no tipo de ação
    let valorId;
    if (ex === "1") {
        // Roleta - usar valores aleatórios da roleta
        valorId = ROLETA_VALORES[randomRange(0, ROLETA_VALORES.length - 1)];
    } else if (ex === "3") {
        // Raspadinha - usar valores aleatórios da raspadinha
        valorId = RASPADINHA_VALORES[randomRange(0, RASPADINHA_VALORES.length - 1)];
    } else if (ex === "2") {
        // Quiz - SEMPRE id: 0 (servidor retorna 25 pontos fixos)
        valorId = QUIZ_VALOR_FIXO;
    } else {
        // Outros - usar valores da roleta como padrão
        valorId = ROLETA_VALORES[randomRange(0, ROLETA_VALORES.length - 1)];
    }
    
    const payload = criarPayload(valorId, ex, 8, xValue);
    const result = await fazerRequest("datas", payload);
    
    if (result.code === 201) {
        const msg = result.msg || 'Ação realizada';
        const balance = result.balance || 0;
        return { sucesso: true, saldo: balance, msg };
    }
    
    const erroMsg = result.msg || result.error || result.message || 'Erro desconhecido';
    return { sucesso: false, saldo: 0, msg: erroMsg };
}

async function executarJogo(idJogo) {
    const payload = criarPayload(idJogo, "", 14, 1);
    const result = await fazerRequest("datas", payload);
    
    if (result.code === 201) {
        const msg = result.msg || 'Ação realizada';
        const balance = result.balance || 0;
        return { sucesso: true, saldo: balance, msg };
    }
    
    const erroMsg = result.msg || result.error || result.message || 'Erro desconhecido';
    return { sucesso: false, saldo: 0, msg: erroMsg };
}

// ============================================
// FUNÇÕES PRINCIPAIS (REFATORADAS)
// ============================================

async function executarRaspadinhas() {
    addLog('=== RASPADINHAS ===', 'info');
    
    await microPause(); // Pausa natural antes de começar
    
    const raspadinhas = await verificarLimite("3");
    addLog(`Raspadinhas disponíveis: ${raspadinhas}`, 'info');
    
    if (raspadinhas === 0) {
        addLog('Nenhuma raspadinha disponível', 'warning');
        document.getElementById('progressRaspadinha').textContent = '0/0';
        return { acoes: 0, ganho: 0 };
    }
    
    let sucessoCount = 0;
    let totalGanho = 0;
    
    // Decidir quantas fazer (85-100% das disponíveis)
    const quantidadeFazer = Math.ceil(raspadinhas * randomRange(85, 100) / 100);
    addLog(`Planejando fazer ${quantidadeFazer} de ${raspadinhas} raspadinhas`, 'info');
    
    for (let i = 0; i < quantidadeFazer; i++) {
        if (!botState.running) break;
        
        // Hesitação antes de clicar
        await hesitacaoHumana();
        
        // CRÍTICO: Assistir vídeo de propaganda
        await assistirVideo('raspadinha');
        
        const xAtual = raspadinhas - i;
        const resultado = await executarAcao("3", "raspadinha", xAtual);
        
        if (resultado.sucesso) {
            sucessoCount++;
            botState.acoesExecutadas++;
            const match = resultado.msg.match(/^(\d+)/);
            if (match) {
                const pontos = parseInt(match[1]);
                totalGanho += pontos;
                botState.ganhoTotal += pontos;
                botState.saldoAtual = resultado.saldo;
                addLog(`✓ Raspadinha ${i+1}/${quantidadeFazer}: +${pontos} pontos`, 'success');
            } else {
                addLog(`✓ Raspadinha ${i+1}/${quantidadeFazer}: ${resultado.msg}`, 'success');
            }
        } else {
            addLog(`✗ Raspadinha ${i+1}/${quantidadeFazer}: ${resultado.msg}`, 'error');
        }
        
        document.getElementById('progressRaspadinha').textContent = `${i+1}/${quantidadeFazer}`;
        atualizarStats();
        
        // Ver resultado
        await verResultado();
        
        // Intervalo natural entre ações
        if (i < quantidadeFazer - 1) {
            await intervaloEntreAcoes();
        }
    }
    
    addLog(`Raspadinhas concluídas: ${sucessoCount}/${quantidadeFazer} (+${totalGanho} pontos)`, 'success');
    return { acoes: sucessoCount, ganho: totalGanho };
}

async function executarRoleta() {
    addLog('=== ROLETA ===', 'info');
    
    await microPause();
    
    const giros = await verificarLimite("1");
    addLog(`Giros disponíveis: ${giros}`, 'info');
    
    if (giros === 0) {
        addLog('Nenhum giro disponível', 'warning');
        document.getElementById('progressRoleta').textContent = '0/0';
        return { acoes: 0, ganho: 0 };
    }
    
    let sucessoCount = 0;
    let totalGanho = 0;
    
    const quantidadeFazer = Math.ceil(giros * randomRange(85, 100) / 100);
    addLog(`Planejando fazer ${quantidadeFazer} de ${giros} giros`, 'info');
    
    for (let i = 0; i < quantidadeFazer; i++) {
        if (!botState.running) break;
        
        await hesitacaoHumana();
        await assistirVideo('roleta');
        
        const xAtual = giros - i;
        const resultado = await executarAcao("1", "roleta", xAtual);
        
        if (resultado.sucesso) {
            sucessoCount++;
            botState.acoesExecutadas++;
            const match = resultado.msg.match(/^(\d+)/);
            if (match) {
                const pontos = parseInt(match[1]);
                totalGanho += pontos;
                botState.ganhoTotal += pontos;
                botState.saldoAtual = resultado.saldo;
                addLog(`✓ Giro ${i+1}/${quantidadeFazer}: +${pontos} pontos`, 'success');
            } else {
                addLog(`✓ Giro ${i+1}/${quantidadeFazer}: ${resultado.msg}`, 'success');
            }
        } else {
            addLog(`✗ Giro ${i+1}/${quantidadeFazer}: ${resultado.msg}`, 'error');
        }
        
        document.getElementById('progressRoleta').textContent = `${i+1}/${quantidadeFazer}`;
        atualizarStats();
        
        await verResultado();
        
        if (i < quantidadeFazer - 1) {
            await intervaloEntreAcoes();
        }
    }
    
    addLog(`Roleta concluída: ${sucessoCount}/${quantidadeFazer} (+${totalGanho} pontos)`, 'success');
    return { acoes: sucessoCount, ganho: totalGanho };
}

async function executarQuiz() {
    addLog('=== QUIZ DE MATEMÁTICA ===', 'info');
    
    await microPause();
    
    const quizzes = await verificarLimite("2");
    addLog(`Quizzes disponíveis: ${quizzes}`, 'info');
    
    if (quizzes === 0) {
        addLog('Nenhum quiz disponível', 'warning');
        document.getElementById('progressQuiz').textContent = '0/0';
        return { acoes: 0, ganho: 0 };
    }
    
    let sucessoCount = 0;
    let totalGanho = 0;
    
    const quantidadeFazer = Math.ceil(quizzes * randomRange(85, 100) / 100);
    addLog(`Planejando fazer ${quantidadeFazer} de ${quizzes} quizzes`, 'info');
    
    for (let i = 0; i < quantidadeFazer; i++) {
        if (!botState.running) break;
        
        await hesitacaoHumana();
        await assistirVideo('quiz');
        
        // Tempo extra para "ler" e "responder" pergunta (5-10s)
        const tempoLeitura = randomRange(5000, 10000);
        addLog(`📖 Lendo pergunta e respondendo... (${(tempoLeitura/1000).toFixed(1)}s)`, 'info');
        await sleep(tempoLeitura);
        
        const xAtual = quizzes - i;
        const resultado = await executarAcao("2", "quiz", xAtual);
        
        if (resultado.sucesso) {
            sucessoCount++;
            botState.acoesExecutadas++;
            const match = resultado.msg.match(/^(\d+)/);
            if (match) {
                const pontos = parseInt(match[1]);
                totalGanho += pontos;
                botState.ganhoTotal += pontos;
                botState.saldoAtual = resultado.saldo;
                addLog(`✓ Quiz ${i+1}/${quantidadeFazer}: +${pontos} pontos`, 'success');
            } else {
                addLog(`✓ Quiz ${i+1}/${quantidadeFazer}: ${resultado.msg}`, 'success');
            }
        } else {
            addLog(`✗ Quiz ${i+1}/${quantidadeFazer}: ${resultado.msg}`, 'error');
        }
        
        document.getElementById('progressQuiz').textContent = `${i+1}/${quantidadeFazer}`;
        atualizarStats();
        
        await verResultado();
        
        if (i < quantidadeFazer - 1) {
            await intervaloEntreAcoes();
        }
    }
    
    addLog(`Quiz concluído: ${sucessoCount}/${quantidadeFazer} (+${totalGanho} pontos)`, 'success');
    return { acoes: sucessoCount, ganho: totalGanho };
}

async function executarTodosJogos() {
    addLog('=== JOGOS (33 IDs) ===', 'info');
    
    await microPause();
    
    addLog('Testando jogos disponíveis...', 'info');
    
    let sucessoCount = 0;
    let totalGanho = 0;
    
    // Embaralhar ordem dos jogos ocasionalmente (30% de chance)
    let jogosParaTestar = [...GAME_IDS];
    if (Math.random() < 0.3) {
        addLog('🔀 Variando ordem dos jogos...', 'info');
        jogosParaTestar.sort(() => Math.random() - 0.5);
    }
    
    // Decidir quantos jogos testar (70-100%)
    const quantidadeTestar = Math.ceil(jogosParaTestar.length * randomRange(70, 100) / 100);
    addLog(`Planejando testar ${quantidadeTestar} de ${jogosParaTestar.length} jogos`, 'info');
    
    for (let i = 0; i < quantidadeTestar; i++) {
        if (!botState.running) break;
        
        const id = jogosParaTestar[i];
        
        await hesitacaoHumana();
        await assistirVideo(`jogo ${id}`);
        
        const resultado = await executarJogo(id);
        
        if (resultado.sucesso) {
            sucessoCount++;
            botState.acoesExecutadas++;
            const match = resultado.msg.match(/^(\d+)/);
            if (match) {
                const pontos = parseInt(match[1]);
                totalGanho += pontos;
                botState.ganhoTotal += pontos;
                botState.saldoAtual = resultado.saldo;
                addLog(`✓ Jogo ID ${id}: +${pontos} pontos`, 'success');
            } else {
                addLog(`✓ Jogo ID ${id}: ${resultado.msg}`, 'success');
            }
        } else {
            if (resultado.msg.includes('já foi reivindicado') || resultado.msg.includes('reivindicado')) {
                addLog(`⏭ Jogo ID ${id}: Já reivindicado hoje`, 'warning');
            } else {
                addLog(`✗ Jogo ID ${id}: ${resultado.msg}`, 'error');
            }
        }
        
        document.getElementById('progressJogos').textContent = `${i+1}/${quantidadeTestar}`;
        atualizarStats();
        
        await verResultado();
        
        if (i < quantidadeTestar - 1) {
            await intervaloEntreAcoes();
        }
    }
    
    addLog(`Jogos concluídos: ${sucessoCount}/${quantidadeTestar} (+${totalGanho} pontos)`, 'success');
    return { acoes: sucessoCount, ganho: totalGanho };
}

// ============================================
// LOOP INFINITO (OTIMIZADO)
// ============================================

async function loopJogos() {
    let tentativa = 0;
    
    while (botState.running && config.autoLoop) {
        tentativa++;
        addLog(`\n=== LOOP INFINITO - Tentativa #${tentativa} ===`, 'info');
        
        let ganhouAlgo = false;
        
        // Embaralhar jogos
        const jogosEmbaralhados = [...GAME_IDS].sort(() => Math.random() - 0.5);
        
        for (const id of jogosEmbaralhados) {
            if (!botState.running) break;
            
            // Só tentar alguns jogos por loop (não todos)
            if (Math.random() < 0.3) continue; // 30% de chance de pular
            
            await hesitacaoHumana();
            await assistirVideo(`jogo ${id}`);
            
            const resultado = await executarJogo(id);
            
            if (resultado.sucesso) {
                ganhouAlgo = true;
                botState.acoesExecutadas++;
                const match = resultado.msg.match(/^(\d+)/);
                if (match) {
                    const pontos = parseInt(match[1]);
                    botState.ganhoTotal += pontos;
                    botState.saldoAtual = resultado.saldo;
                    addLog(`✓ Jogo ID ${id}: +${pontos} pontos`, 'success');
                    atualizarStats();
                }
            }
            
            await verResultado();
            await intervaloEntreAcoes();
        }
        
        if (!ganhouAlgo) {
            addLog('⏳ Nenhum jogo disponível no momento', 'warning');
        }
        
        if (botState.running && config.autoLoop) {
            // Intervalo realista: 5-15 minutos (baseado na análise)
            const intervaloMinutos = randomRange(5, 15);
            const intervaloMs = intervaloMinutos * 60 * 1000;
            addLog(`⏳ Aguardando ${intervaloMinutos} minutos até próxima tentativa...`, 'info');
            await sleep(intervaloMs);
        }
    }
}

// ============================================
// FUNÇÃO PRINCIPAL
// ============================================

async function iniciarBot(e) {
    e.preventDefault();
    
    // Carregar biblioteca MD5
    try {
        await loadMD5Library();
    } catch (error) {
        addLog('Erro ao carregar biblioteca MD5. Verifique sua conexão.', 'error');
        return;
    }
    
    // Obter configurações do formulário
    config.bearerToken = document.getElementById('bearerToken').value.trim();
    config.exId = document.getElementById('exId').value.trim();
    config.secretKey = document.getElementById('secretKey').value.trim();
    config.autoLoop = document.getElementById('autoLoop').checked;
    
    if (!config.bearerToken || !config.exId) {
        addLog('Por favor, preencha Bearer Token e EX ID', 'error');
        return;
    }
    
    // Iniciar bot
    botState.running = true;
    configSection.style.display = 'none';
    statusSection.style.display = 'block';
    
    limparLogs();
    addLog('🚀 Bot iniciado - Versão 100% Legit!', 'success');
    addLog('✨ Simulação completa de comportamento humano ativada', 'success');
    addLog('Validando token...', 'info');
    
    // Verificar saldo inicial
    const saldoInicial = await verSaldo();
    
    if (saldoInicial < 0) {
        addLog('✗ Token inválido ou expirado!', 'error');
        addLog('Obtenha um novo token usando HTTP Catcher', 'warning');
        pararBot();
        return;
    }
    
    botState.saldoInicial = saldoInicial;
    botState.saldoAtual = saldoInicial;
    botState.ganhoTotal = 0;
    botState.acoesExecutadas = 0;
    
    addLog(`✓ Token válido! Saldo inicial: ${saldoInicial}`, 'success');
    atualizarStats();
    
    // Pausa inicial (simular navegação)
    await microPause();
    
    // Decidir ordem das ações (variar 30% das vezes)
    const acoes = ['raspadinhas', 'roleta', 'quiz', 'jogos'];
    if (Math.random() < 0.3) {
        addLog('🔀 Variando ordem das ações...', 'info');
        acoes.sort(() => Math.random() - 0.5);
    }
    
    // Executar ações na ordem decidida
    for (const acao of acoes) {
        if (!botState.running) break;
        
        switch(acao) {
            case 'raspadinhas':
                await executarRaspadinhas();
                break;
            case 'roleta':
                await executarRoleta();
                break;
            case 'quiz':
                await executarQuiz();
                break;
            case 'jogos':
                await executarTodosJogos();
                break;
        }
        
        // Pausa entre categorias
        if (botState.running) {
            await microPause();
        }
    }
    
    if (!botState.running) return;
    
    // Atualizar saldo final
    botState.saldoAtual = await verSaldo();
    atualizarStats();
    
    addLog('\n=== RESUMO FINAL ===', 'info');
    addLog(`Ações executadas: ${botState.acoesExecutadas}`, 'info');
    addLog(`Saldo inicial: ${botState.saldoInicial}`, 'info');
    addLog(`Saldo final: ${botState.saldoAtual}`, 'info');
    addLog(`Ganho total: +${botState.ganhoTotal}`, 'success');
    
    // Iniciar loop se ativado
    if (config.autoLoop && botState.running) {
        addLog('\n🔄 Iniciando loop infinito com intervalos realistas...', 'info');
        await loopJogos();
    } else {
        addLog('✓ Execução concluída!', 'success');
        pararBot();
    }
}

function pararBot() {
    botState.running = false;
    if (botState.loopInterval) {
        clearInterval(botState.loopInterval);
        botState.loopInterval = null;
    }
    
    addLog('⏹️ Bot parado pelo usuário', 'warning');
    
    setTimeout(() => {
        configSection.style.display = 'block';
        statusSection.style.display = 'none';
    }, 2000);
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Inicialização
addLog('Bem-vindo ao Graninha Bot - Versão 100% Legit!', 'success');
addLog('✨ Simulação completa de comportamento humano', 'info');
addLog('📺 Vídeos de propaganda simulados (25-40s cada)', 'info');
addLog('⏱️ Timing variável e natural', 'info');
addLog('Configure suas credenciais e clique em Iniciar Bot', 'info');
