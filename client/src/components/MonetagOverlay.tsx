import { useEffect } from 'react';

/*
 * SISTEMA GLOBAL DE OVERLAY MONETAG
 * Este componente instala interceptadores globais para detectar cliques
 * e mostrar o overlay de bloqueio em qualquer tela do aplicativo.
 * 
 * IMPORTANTE: O overlay usa técnicas avançadas para ficar ACIMA de tudo,
 * incluindo iframes e elementos do Monetag com z-index alto.
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';
const EMAIL_STORAGE_KEY = 'youngmoney_email';
const POSTBACK_SERVER = 'https://monetag-postback-server-production.up.railway.app';
const POSTBACK_URL = `${POSTBACK_SERVER}/api/postback`;
const ZONE_ID = '10325249';

// Duração do overlay em segundos
const OVERLAY_DURATION = 15;

// Declaração global para o sistema de overlay
declare global {
  interface Window {
    __MONETAG_INTERCEPTORS_INSTALLED__?: boolean;
    MontagOverlay?: {
      show: () => void;
      hide: () => void;
    };
    [key: string]: any;
  }
}

// Função para criar o overlay flutuante que fica ACIMA DE TUDO
function createFloatingOverlay() {
  // Verificar se já existe um overlay
  if (document.getElementById('monetag-block-overlay')) {
    console.log('[OVERLAY] Overlay já existe, ignorando...');
    return;
  }

  console.log('[OVERLAY] 🎯 CLIQUE DETECTADO! Criando overlay flutuante de ' + OVERLAY_DURATION + ' segundos...');

  // ========================================
  // TÉCNICA 1: Criar estilo global com !important em tudo
  // ========================================
  if (!document.getElementById('monetag-overlay-critical-styles')) {
    const criticalStyle = document.createElement('style');
    criticalStyle.id = 'monetag-overlay-critical-styles';
    criticalStyle.textContent = `
      /* Forçar overlay acima de TUDO */
      #monetag-block-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        min-width: 100vw !important;
        min-height: 100vh !important;
        max-width: 100vw !important;
        max-height: 100vh !important;
        z-index: 2147483647 !important;
        background: rgba(10, 14, 39, 0.95) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        pointer-events: all !important;
        isolation: isolate !important;
        contain: layout style paint !important;
        transform: translateZ(0) !important;
        will-change: transform !important;
      }
      
      /* Esconder TODOS os iframes quando overlay estiver ativo */
      body.monetag-overlay-active iframe,
      body.monetag-overlay-active [id*="monetag"],
      body.monetag-overlay-active [id*="Monetag"],
      body.monetag-overlay-active [class*="monetag"],
      body.monetag-overlay-active [class*="Monetag"],
      body.monetag-overlay-active [id*="ad-"],
      body.monetag-overlay-active [class*="ad-"],
      body.monetag-overlay-active [data-zone],
      html.monetag-overlay-active iframe,
      html.monetag-overlay-active [id*="monetag"],
      html.monetag-overlay-active [id*="Monetag"],
      html.monetag-overlay-active [class*="monetag"],
      html.monetag-overlay-active [class*="Monetag"] {
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
        z-index: -1 !important;
      }
      
      /* Garantir que o overlay-content fique visível */
      #monetag-block-overlay * {
        visibility: visible !important;
        opacity: 1 !important;
      }
      
      @keyframes fadeInOverlay {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      @keyframes scaleInOverlay {
        from { opacity: 0; transform: scale(0.9) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
      }
      @keyframes pulseGlow {
        0%, 100% { box-shadow: 0 0 20px rgba(0, 221, 255, 0.3), 0 0 40px rgba(139, 92, 246, 0.2); }
        50% { box-shadow: 0 0 30px rgba(0, 221, 255, 0.5), 0 0 60px rgba(139, 92, 246, 0.3); }
      }
    `;
    document.head.insertBefore(criticalStyle, document.head.firstChild);
  }

  // ========================================
  // TÉCNICA 2: Adicionar classe ao body e html para esconder iframes
  // ========================================
  document.body.classList.add('monetag-overlay-active');
  document.documentElement.classList.add('monetag-overlay-active');

  // ========================================
  // TÉCNICA 3: Criar overlay no documentElement (mais alto que body)
  // ========================================
  const overlay = document.createElement('div');
  overlay.id = 'monetag-block-overlay';
  
  // Aplicar estilos inline também (redundância para garantir)
  overlay.style.cssText = `
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 2147483647 !important;
    background: rgba(10, 14, 39, 0.95) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    pointer-events: all !important;
    animation: fadeInOverlay 0.3s ease !important;
  `;

  // Bloquear TODOS os eventos no overlay
  const blockEvent = (e: Event) => {
    e.stopPropagation();
    e.stopImmediatePropagation();
    e.preventDefault();
    console.log('[OVERLAY] Evento ' + e.type + ' bloqueado');
    return false;
  };

  ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend', 'touchmove', 
   'contextmenu', 'pointerdown', 'pointerup', 'pointermove', 'dblclick',
   'wheel', 'scroll'].forEach(eventType => {
    overlay.addEventListener(eventType, blockEvent, { capture: true, passive: false });
  });

  // Mensagem do overlay com contador
  const message = document.createElement('div');
  message.style.cssText = `
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.95) 0%, rgba(22, 33, 62, 0.95) 100%) !important;
    padding: 40px 50px !important;
    border-radius: 24px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5), 0 0 30px rgba(139, 92, 246, 0.3) !important;
    text-align: center !important;
    max-width: 90% !important;
    width: 400px !important;
    animation: scaleInOverlay 0.4s ease, pulseGlow 2s ease-in-out infinite !important;
    pointer-events: none !important;
    border: 2px solid rgba(0, 221, 255, 0.4) !important;
  `;

  message.innerHTML = `
    <div style="width: 80px; height: 80px; margin: 0 auto 20px; position: relative;">
      <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
        <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(139, 92, 246, 0.3)" stroke-width="6"></circle>
        <circle id="overlay-progress-circle" cx="50" cy="50" r="45" fill="none" stroke="#00ddff" stroke-width="6" stroke-linecap="round" stroke-dasharray="283" stroke-dashoffset="0" style="transition: stroke-dashoffset 1s linear; filter: drop-shadow(0 0 8px #00ddff);"></circle>
      </svg>
      <span id="overlay-timer" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: 'Space Grotesk', 'Orbitron', monospace; font-size: 28px; font-weight: 700; color: #00ddff; text-shadow: 0 0 10px rgba(0, 221, 255, 0.5);">${OVERLAY_DURATION}</span>
    </div>
    <h3 style="margin: 0 0 10px 0; color: #ffffff; font-size: 18px; font-family: 'Space Grotesk', sans-serif; font-weight: 600;">Processando sua tarefa...</h3>
    <p style="margin: 0; color: #a0aec0; font-size: 14px; font-family: 'Inter', sans-serif;">Aguarde enquanto validamos seu clique</p>
  `;

  overlay.appendChild(message);
  
  // Inserir no documentElement como último filho (fica acima de tudo)
  document.documentElement.appendChild(overlay);

  // ========================================
  // TÉCNICA 4: Forçar overlay no topo continuamente
  // ========================================
  const forceTopInterval = setInterval(() => {
    const overlayEl = document.getElementById('monetag-block-overlay');
    if (!overlayEl) {
      clearInterval(forceTopInterval);
      return;
    }

    // Garantir que está no documentElement como último filho
    if (overlayEl.parentNode !== document.documentElement) {
      document.documentElement.appendChild(overlayEl);
    } else if (overlayEl !== document.documentElement.lastElementChild) {
      document.documentElement.appendChild(overlayEl);
    }

    // Forçar z-index máximo
    overlayEl.style.zIndex = '2147483647';
    
    // Esconder qualquer iframe que apareça
    document.querySelectorAll('iframe').forEach(iframe => {
      if (!iframe.closest('#monetag-block-overlay')) {
        (iframe as HTMLElement).style.cssText += 'visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; z-index: -1 !important;';
      }
    });
    
    // Esconder elementos do Monetag
    document.querySelectorAll('[id*="monetag"], [id*="Monetag"], [class*="monetag"], [class*="Monetag"], [data-zone]').forEach(el => {
      if (!el.closest('#monetag-block-overlay') && el.id !== 'monetag-block-overlay') {
        (el as HTMLElement).style.cssText += 'visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; z-index: -1 !important;';
      }
    });
  }, 50); // Verificar a cada 50ms

  // ========================================
  // TÉCNICA 5: Contador regressivo
  // ========================================
  const circumference = 283;
  let countdown = OVERLAY_DURATION;

  const countdownInterval = setInterval(() => {
    countdown--;

    const timerEl = document.getElementById('overlay-timer');
    const progressCircle = document.getElementById('overlay-progress-circle');

    if (timerEl) timerEl.textContent = String(countdown);

    if (progressCircle) {
      const progress = (OVERLAY_DURATION - countdown) / OVERLAY_DURATION;
      progressCircle.style.strokeDashoffset = String(circumference * (1 - progress));
    }

    console.log('[OVERLAY] Contador: ' + countdown + ' segundos restantes');

    if (countdown <= 0) {
      clearInterval(countdownInterval);
      clearInterval(forceTopInterval);
      
      // Remover classes
      document.body.classList.remove('monetag-overlay-active');
      document.documentElement.classList.remove('monetag-overlay-active');
      
      console.log('[OVERLAY] Contador finalizado! Reiniciando página...');
      window.location.reload();
    }
  }, 1000);

  console.log('[OVERLAY] ✅ Overlay criado com sucesso e forçado ao topo!');
}

// Função para remover o overlay
function removeOverlay() {
  const overlay = document.getElementById('monetag-block-overlay');
  if (overlay) {
    overlay.remove();
  }
  document.body.classList.remove('monetag-overlay-active');
  document.documentElement.classList.remove('monetag-overlay-active');
}

// Função para enviar postback
function sendPostbackToNewServer(eventType: string) {
  console.log('[POSTBACK] Enviando ' + eventType + ' para novo servidor');

  // 🎯 SE FOR CLIQUE, MOSTRAR OVERLAY IMEDIATAMENTE!
  if (eventType === 'click') {
    console.log('[POSTBACK] 🎯 CLIQUE DETECTADO! Mostrando overlay...');
    createFloatingOverlay();
  }

  // Obter ymid e email do localStorage
  const ymid = localStorage.getItem(YMID_STORAGE_KEY) || 'unknown';
  const userEmail = localStorage.getItem(EMAIL_STORAGE_KEY) || 'unknown@youngmoney.com';

  const params = new URLSearchParams({
    event_type: eventType,
    zone_id: ZONE_ID,
    ymid: ymid,
    user_email: userEmail,
    estimated_price: eventType === 'click' ? '0.0045' : '0.0023'
  });

  const url = `${POSTBACK_URL}?${params.toString()}`;
  console.log('[POSTBACK] URL:', url);

  fetch(url, { method: 'GET', mode: 'cors' })
    .then(response => response.json())
    .then(data => console.log('[POSTBACK] ✅ ' + eventType + ' enviado:', data))
    .catch(err => console.error('[POSTBACK] ❌ Erro:', err));
}

// Função para instalar os interceptadores
function installInterceptors() {
  // Evitar duplicação
  if (window.__MONETAG_INTERCEPTORS_INSTALLED__) {
    console.log('[INTERCEPTOR] Interceptadores já instalados, ignorando...');
    return;
  }
  window.__MONETAG_INTERCEPTORS_INSTALLED__ = true;

  console.log('[OVERLAY SYSTEM] Iniciado globalmente - Duração: ' + OVERLAY_DURATION + ' segundos');

  // Expor globalmente
  window.MontagOverlay = {
    show: createFloatingOverlay,
    hide: removeOverlay
  };

  // ========================================
  // 1. INTERCEPTAR fetch()
  // ========================================
  const originalFetch = window.fetch;
  window.fetch = function(...args: any[]) {
    const url = args[0];
    if (typeof url === 'string' && url.includes('youngmoney-api-railway')) {
      if (url.includes('%7Bymid%7D') || url.includes('{ymid}')) {
        console.log('[BLOQUEIO FETCH] 🚫 Postback do Monetag bloqueado:', url);
        const eventType = url.includes('event_type=click') || url.includes('event_type%3Dclick') ? 'click' : 'impression';
        console.log('[BLOQUEIO FETCH] Tipo:', eventType);
        sendPostbackToNewServer(eventType);
        return Promise.resolve(new Response('', { status: 200 }));
      }
    }
    return originalFetch.apply(window, args);
  };

  // ========================================
  // 2. INTERCEPTAR XMLHttpRequest
  // ========================================
  const originalXHROpen = XMLHttpRequest.prototype.open;
  (XMLHttpRequest.prototype as any).open = function(method: string, url: string, ...rest: any[]) {
    if (typeof url === 'string' && url.includes('youngmoney-api-railway')) {
      if (url.includes('%7Bymid%7D') || url.includes('{ymid}')) {
        console.log('[BLOQUEIO XHR] 🚫 Postback do Monetag bloqueado:', url);
        const eventType = url.includes('event_type=click') || url.includes('event_type%3Dclick') ? 'click' : 'impression';
        console.log('[BLOQUEIO XHR] Tipo:', eventType);
        sendPostbackToNewServer(eventType);
        return originalXHROpen.call(this, method, 'about:blank', ...rest);
      }
    }
    return originalXHROpen.call(this, method, url, ...rest);
  };

  // ========================================
  // 3. INTERCEPTAR Image (pixel tracking)
  // ========================================
  const OriginalImage = window.Image;
  (window as any).Image = function() {
    const img = new OriginalImage();
    const originalSrcDescriptor = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, 'src');
    if (originalSrcDescriptor && originalSrcDescriptor.set) {
      const originalSrcSetter = originalSrcDescriptor.set;
      Object.defineProperty(img, 'src', {
        set: function(value: string) {
          if (typeof value === 'string' && value.includes('youngmoney-api-railway')) {
            if (value.includes('%7Bymid%7D') || value.includes('{ymid}')) {
              console.log('[BLOQUEIO IMG] 🚫 Postback do Monetag bloqueado:', value);
              const eventType = value.includes('event_type=click') || value.includes('event_type%3Dclick') ? 'click' : 'impression';
              console.log('[BLOQUEIO IMG] Tipo:', eventType);
              sendPostbackToNewServer(eventType);
              return;
            }
          }
          originalSrcSetter.call(this, value);
        },
        get: function() {
          return this.getAttribute('src');
        }
      });
    }
    return img;
  };

  console.log('[INTERCEPTOR] ✅ Interceptadores globais instalados (fetch, XHR, Image)');
}

// Componente React que instala os interceptadores ao montar
export default function MonetagOverlay() {
  useEffect(() => {
    // Instalar interceptadores quando o componente montar
    installInterceptors();
  }, []);

  // Este componente não renderiza nada visualmente
  // O overlay é criado dinamicamente via DOM quando um clique é detectado
  return null;
}

// Exportar funções para uso externo se necessário
export { createFloatingOverlay, installInterceptors, removeOverlay };
