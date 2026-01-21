import { useEffect } from 'react';

/*
 * SISTEMA GLOBAL DE OVERLAY MONETAG
 * Este componente instala interceptadores globais para detectar cliques
 * e mostrar o overlay de bloqueio em qualquer tela do aplicativo.
 * Deve ser renderizado no App.tsx para funcionar globalmente.
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

// Função para criar o overlay flutuante (definida fora do componente para ser acessível globalmente)
function createFloatingOverlay() {
  // Verificar se já existe um overlay
  if (document.getElementById('monetag-block-overlay')) {
    console.log('[OVERLAY] Overlay já existe, ignorando...');
    return;
  }

  console.log('[OVERLAY] 🎯 CLIQUE DETECTADO! Criando overlay flutuante de ' + OVERLAY_DURATION + ' segundos...');

  // Criar overlay TRANSPARENTE que BLOQUEIA CLIQUES
  const overlay = document.createElement('div');
  overlay.id = 'monetag-block-overlay';
  overlay.style.cssText = `
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0, 0, 0, 0.1) !important;
    z-index: 2147483647 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    animation: fadeInOverlay 0.3s ease !important;
    pointer-events: auto !important;
  `;

  // Bloquear TODOS os eventos
  ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend', 'touchmove', 'contextmenu', 'pointerdown', 'pointerup'].forEach(function(eventType) {
    overlay.addEventListener(eventType, function(e) {
      e.stopPropagation();
      e.preventDefault();
      console.log('[OVERLAY] Evento ' + eventType + ' bloqueado');
      return false;
    }, true);
  });

  // Mensagem do overlay com contador
  const message = document.createElement('div');
  message.style.cssText = `
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.6) 0%, rgba(22, 33, 62, 0.6) 100%) !important;
    padding: 35px 40px !important;
    border-radius: 20px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3), 0 0 20px rgba(139, 92, 246, 0.2) !important;
    text-align: center !important;
    max-width: 90% !important;
    width: 380px !important;
    animation: scaleInOverlay 0.4s ease !important;
    pointer-events: auto !important;
    border: 2px solid rgba(139, 92, 246, 0.3) !important;
    opacity: 0.85 !important;
  `;

  message.innerHTML = `
    <div style="width: 40px; height: 40px; margin: 0 auto 15px; position: relative;">
      <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
        <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(139, 92, 246, 0.2)" stroke-width="8"></circle>
        <circle id="overlay-progress-circle" cx="50" cy="50" r="45" fill="none" stroke="#00ddff" stroke-width="8" stroke-linecap="round" stroke-dasharray="283" stroke-dashoffset="0" style="transition: stroke-dashoffset 1s linear;"></circle>
      </svg>
      <span id="overlay-timer" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: 'Orbitron', monospace; font-size: 14px; font-weight: 700; color: #00ddff;">${OVERLAY_DURATION}</span>
    </div>
    <p style="margin: 0; color: #a0aec0; font-size: 14px; font-family: 'Inter', sans-serif;">Realizando tarefa automaticamente...</p>
  `;

  overlay.appendChild(message);
  document.documentElement.appendChild(overlay);

  // Adicionar animações CSS
  if (!document.getElementById('overlay-animations-style')) {
    const style = document.createElement('style');
    style.id = 'overlay-animations-style';
    style.textContent = `
      @keyframes fadeInOverlay {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      @keyframes scaleInOverlay {
        from { opacity: 0; transform: scale(0.9) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
      }
      #monetag-block-overlay {
        z-index: 2147483647 !important;
        position: fixed !important;
      }
    `;
    document.head.appendChild(style);
  }

  // Manter overlay no topo
  const topInterval = setInterval(function() {
    const overlayEl = document.getElementById('monetag-block-overlay');
    if (!overlayEl) {
      clearInterval(topInterval);
      return;
    }
    if (overlayEl.parentNode !== document.documentElement || overlayEl !== document.documentElement.lastElementChild) {
      document.documentElement.appendChild(overlayEl);
    }
    overlayEl.style.zIndex = '2147483647';
  }, 100);

  // Contador regressivo
  const circumference = 283;
  let countdown = OVERLAY_DURATION;

  const countdownInterval = setInterval(function() {
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
      clearInterval(topInterval);
      console.log('[OVERLAY] Contador finalizado! Reiniciando página...');
      window.location.reload();
    }
  }, 1000);

  console.log('[OVERLAY] ✅ Overlay criado com sucesso!');
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
    hide: function() {
      const overlay = document.getElementById('monetag-block-overlay');
      if (overlay) {
        overlay.remove();
      }
    }
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
export { createFloatingOverlay, installInterceptors };
