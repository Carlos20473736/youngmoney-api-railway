import { useEffect } from 'react';

/*
 * SISTEMA GLOBAL DE OVERLAY MONETAG
 * 
 * Este overlay usa técnicas MUITO agressivas para garantir que apareça
 * sobre qualquer elemento do Monetag, incluindo:
 * - Injeção de CSS inline com !important
 * - Remoção de todos os elementos concorrentes
 * - Uso de position: fixed no body
 * - Forçar repaint do DOM
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';
const EMAIL_STORAGE_KEY = 'youngmoney_email';
const POSTBACK_SERVER = 'https://monetag-postback-server-production.up.railway.app';
const POSTBACK_URL = `${POSTBACK_SERVER}/api/postback`;
const ZONE_ID = '10325249';

const OVERLAY_DURATION = 15;
const DEBOUNCE_TIME = 2000;

declare global {
  interface Window {
    __MONETAG_INTERCEPTORS_INSTALLED__?: boolean;
    __LAST_IMPRESSION_TIME__?: number;
    __LAST_CLICK_TIME__?: number;
    MontagOverlay?: {
      show: () => void;
      hide: () => void;
    };
    [key: string]: any;
  }
}

// ========================================
// FUNÇÃO PRINCIPAL: CRIAR OVERLAY VISÍVEL
// ========================================
function createFloatingOverlay() {
  console.log('[GLOBAL OVERLAY] Iniciando criação do overlay...');
  
  // Verificar se já existe
  if (document.getElementById('ym-global-overlay')) {
    console.log('[GLOBAL OVERLAY] Overlay já existe');
    return;
  }

  // ========================================
  // PASSO 1: Remover TUDO do Monetag primeiro
  // ========================================
  const removeAllMonetag = () => {
    // Remover todos os iframes
    const iframes = document.querySelectorAll('iframe');
    iframes.forEach(iframe => {
      console.log('[GLOBAL OVERLAY] Removendo iframe');
      iframe.style.display = 'none';
      iframe.style.visibility = 'hidden';
      iframe.style.opacity = '0';
      iframe.style.pointerEvents = 'none';
      iframe.remove();
    });

    // Remover elementos com data-zone ou relacionados ao Monetag
    const monetagElements = document.querySelectorAll('[data-zone], [id*="monetag"], [class*="monetag"], [id*="ad"], [class*="ad-"]');
    monetagElements.forEach(el => {
      if (el.id !== 'ym-global-overlay') {
        console.log('[GLOBAL OVERLAY] Removendo elemento:', el.tagName, el.id || el.className);
        (el as HTMLElement).style.display = 'none';
        el.remove();
      }
    });

    // Remover divs com position fixed que não são nosso overlay
    const fixedElements = document.querySelectorAll('div[style*="position: fixed"], div[style*="position:fixed"]');
    fixedElements.forEach(el => {
      if (el.id !== 'ym-global-overlay' && !el.closest('#ym-global-overlay')) {
        console.log('[GLOBAL OVERLAY] Removendo elemento fixed:', el.id || el.className);
        el.remove();
      }
    });
  };

  // Executar remoção imediatamente
  removeAllMonetag();

  // ========================================
  // PASSO 2: Criar o overlay
  // ========================================
  const overlay = document.createElement('div');
  overlay.id = 'ym-global-overlay';
  
  // CSS inline MUITO agressivo
  overlay.setAttribute('style', `
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    min-width: 100vw !important;
    min-height: 100vh !important;
    max-width: 100vw !important;
    max-height: 100vh !important;
    background-color: rgba(10, 14, 39, 0.99) !important;
    z-index: 2147483647 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    outline: none !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    pointer-events: auto !important;
    visibility: visible !important;
    opacity: 1 !important;
    transform: none !important;
    transition: none !important;
    isolation: isolate !important;
  `);

  // Conteúdo do overlay
  overlay.innerHTML = `
    <div style="
      background: linear-gradient(135deg, rgba(26, 26, 46, 0.98) 0%, rgba(22, 33, 62, 0.98) 100%);
      padding: 40px 50px;
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.5), 0 0 30px rgba(139, 92, 246, 0.3);
      text-align: center;
      max-width: 90%;
      width: 400px;
      border: 2px solid rgba(0, 221, 255, 0.4);
      position: relative;
      z-index: 2147483647;
    ">
      <div style="width: 80px; height: 80px; margin: 0 auto 20px; position: relative;">
        <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
          <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(139, 92, 246, 0.3)" stroke-width="6"></circle>
          <circle id="ym-progress-circle" cx="50" cy="50" r="45" fill="none" stroke="#00ddff" stroke-width="6" stroke-linecap="round" stroke-dasharray="283" stroke-dashoffset="0" style="transition: stroke-dashoffset 1s linear; filter: drop-shadow(0 0 8px #00ddff);"></circle>
        </svg>
        <span id="ym-timer" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: 'Space Grotesk', monospace; font-size: 28px; font-weight: 700; color: #00ddff; text-shadow: 0 0 10px rgba(0, 221, 255, 0.5);">${OVERLAY_DURATION}</span>
      </div>
      <h3 style="margin: 0 0 10px 0; color: #ffffff; font-size: 18px; font-family: 'Space Grotesk', sans-serif; font-weight: 600;">Processando sua tarefa...</h3>
      <p style="margin: 0; color: #a0aec0; font-size: 14px; font-family: 'Inter', sans-serif;">Aguarde enquanto validamos seu clique</p>
    </div>
  `;

  // Bloquear todos os eventos
  const blockEvent = (e: Event) => {
    e.stopPropagation();
    e.stopImmediatePropagation();
    e.preventDefault();
    return false;
  };

  ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend', 'touchmove', 
   'contextmenu', 'pointerdown', 'pointerup', 'pointermove', 'wheel', 'scroll'].forEach(eventType => {
    overlay.addEventListener(eventType, blockEvent, { capture: true, passive: false });
  });

  // ========================================
  // PASSO 3: Adicionar ao DOM de forma agressiva
  // ========================================
  
  // Primeiro, esconder o body temporariamente
  const originalBodyStyle = document.body.getAttribute('style') || '';
  document.body.style.overflow = 'hidden';
  
  // Adicionar overlay como primeiro filho do body
  if (document.body.firstChild) {
    document.body.insertBefore(overlay, document.body.firstChild);
  } else {
    document.body.appendChild(overlay);
  }

  // Também adicionar ao documentElement para garantir
  const overlayClone = overlay.cloneNode(true) as HTMLElement;
  overlayClone.id = 'ym-global-overlay-backup';
  document.documentElement.appendChild(overlayClone);

  // Forçar repaint
  overlay.offsetHeight;
  
  console.log('[GLOBAL OVERLAY] Overlay criado com sucesso!');

  // ========================================
  // PASSO 4: Loop de proteção - manter overlay visível
  // ========================================
  const protectionInterval = setInterval(() => {
    // Remover qualquer coisa que apareça
    removeAllMonetag();
    
    // Garantir que nosso overlay está visível
    const mainOverlay = document.getElementById('ym-global-overlay');
    const backupOverlay = document.getElementById('ym-global-overlay-backup');
    
    if (mainOverlay) {
      mainOverlay.style.cssText = `
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background-color: rgba(10, 14, 39, 0.99) !important;
        z-index: 2147483647 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
      `;
      
      // Mover para o final do body se não estiver lá
      if (mainOverlay.parentNode !== document.body || mainOverlay !== document.body.lastElementChild) {
        document.body.appendChild(mainOverlay);
      }
    }
    
    if (backupOverlay) {
      backupOverlay.style.cssText = `
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background-color: rgba(10, 14, 39, 0.99) !important;
        z-index: 2147483647 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
      `;
    }
  }, 50);

  // ========================================
  // PASSO 5: Contador regressivo
  // ========================================
  const circumference = 283;
  let countdown = OVERLAY_DURATION;

  const countdownInterval = setInterval(() => {
    countdown--;

    // Atualizar em ambos os overlays
    ['ym-timer', 'ym-global-overlay-backup'].forEach(prefix => {
      const timerEl = document.getElementById(prefix === 'ym-timer' ? 'ym-timer' : 'ym-timer');
      const progressCircle = document.getElementById(prefix === 'ym-timer' ? 'ym-progress-circle' : 'ym-progress-circle');

      if (timerEl) timerEl.textContent = String(countdown);

      if (progressCircle) {
        const progress = (OVERLAY_DURATION - countdown) / OVERLAY_DURATION;
        progressCircle.style.strokeDashoffset = String(circumference * (1 - progress));
      }
    });

    console.log('[GLOBAL OVERLAY] Timer: ' + countdown);

    if (countdown <= 0) {
      clearInterval(countdownInterval);
      clearInterval(protectionInterval);
      
      // Restaurar body
      document.body.setAttribute('style', originalBodyStyle);
      
      console.log('[GLOBAL OVERLAY] Finalizado! Recarregando...');
      window.location.reload();
    }
  }, 1000);
}

function removeOverlay() {
  const overlay = document.getElementById('ym-global-overlay');
  const backup = document.getElementById('ym-global-overlay-backup');
  if (overlay) overlay.remove();
  if (backup) backup.remove();
}

function shouldProcessEvent(eventType: string): boolean {
  const now = Date.now();
  
  if (eventType === 'impression') {
    const lastTime = window.__LAST_IMPRESSION_TIME__ || 0;
    if (now - lastTime < DEBOUNCE_TIME) {
      console.log('[DEBOUNCE] Impressão ignorada');
      return false;
    }
    window.__LAST_IMPRESSION_TIME__ = now;
    return true;
  }
  
  if (eventType === 'click') {
    const lastTime = window.__LAST_CLICK_TIME__ || 0;
    if (now - lastTime < DEBOUNCE_TIME) {
      console.log('[DEBOUNCE] Clique ignorado');
      return false;
    }
    window.__LAST_CLICK_TIME__ = now;
    return true;
  }
  
  return true;
}

function sendPostbackToNewServer(eventType: string) {
  if (!shouldProcessEvent(eventType)) {
    return;
  }

  console.log('[POSTBACK] Enviando ' + eventType);

  if (eventType === 'click') {
    console.log('[POSTBACK] 🎯 CLIQUE! Mostrando overlay...');
    // Chamar imediatamente
    createFloatingOverlay();
  }

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

  fetch(url, { method: 'GET', mode: 'cors' })
    .then(response => response.json())
    .then(data => console.log('[POSTBACK] ✅ ' + eventType + ' enviado:', data))
    .catch(err => console.error('[POSTBACK] ❌ Erro:', err));
}

function installInterceptors() {
  if (window.__MONETAG_INTERCEPTORS_INSTALLED__) {
    console.log('[INTERCEPTOR] Já instalados');
    return;
  }
  window.__MONETAG_INTERCEPTORS_INSTALLED__ = true;
  window.__LAST_IMPRESSION_TIME__ = 0;
  window.__LAST_CLICK_TIME__ = 0;

  console.log('[INTERCEPTOR] Instalando interceptadores...');

  window.MontagOverlay = {
    show: createFloatingOverlay,
    hide: removeOverlay
  };

  // 1. Interceptar fetch
  const originalFetch = window.fetch;
  window.fetch = function(...args: any[]) {
    const url = args[0];
    if (typeof url === 'string' && url.includes('youngmoney-api-railway')) {
      if (url.includes('%7Bymid%7D') || url.includes('{ymid}')) {
        console.log('[FETCH] Bloqueado:', url);
        const eventType = url.includes('event_type=click') || url.includes('event_type%3Dclick') ? 'click' : 'impression';
        sendPostbackToNewServer(eventType);
        return Promise.resolve(new Response('', { status: 200 }));
      }
    }
    return originalFetch.apply(window, args);
  };

  // 2. Interceptar XHR
  const originalXHROpen = XMLHttpRequest.prototype.open;
  (XMLHttpRequest.prototype as any).open = function(method: string, url: string, ...rest: any[]) {
    if (typeof url === 'string' && url.includes('youngmoney-api-railway')) {
      if (url.includes('%7Bymid%7D') || url.includes('{ymid}')) {
        console.log('[XHR] Bloqueado:', url);
        const eventType = url.includes('event_type=click') || url.includes('event_type%3Dclick') ? 'click' : 'impression';
        sendPostbackToNewServer(eventType);
        return originalXHROpen.call(this, method, 'about:blank', ...rest);
      }
    }
    return originalXHROpen.call(this, method, url, ...rest);
  };

  // 3. Interceptar Image
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
              console.log('[IMG] Bloqueado:', value);
              const eventType = value.includes('event_type=click') || value.includes('event_type%3Dclick') ? 'click' : 'impression';
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

  // 4. Interceptar sendBeacon
  if (navigator.sendBeacon) {
    const originalSendBeacon = navigator.sendBeacon.bind(navigator);
    navigator.sendBeacon = function(url: string, data?: BodyInit | null) {
      if (typeof url === 'string' && url.includes('youngmoney-api-railway')) {
        if (url.includes('%7Bymid%7D') || url.includes('{ymid}')) {
          console.log('[BEACON] Bloqueado:', url);
          const eventType = url.includes('event_type=click') || url.includes('event_type%3Dclick') ? 'click' : 'impression';
          sendPostbackToNewServer(eventType);
          return true;
        }
      }
      return originalSendBeacon(url, data);
    };
  }

  console.log('[INTERCEPTOR] ✅ Todos instalados');
}

export default function MonetagOverlay() {
  useEffect(() => {
    installInterceptors();
  }, []);

  return null;
}

export { createFloatingOverlay, installInterceptors, removeOverlay };
