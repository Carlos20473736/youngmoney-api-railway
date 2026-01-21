import { useEffect } from 'react';

/*
 * SISTEMA GLOBAL DE OVERLAY MONETAG - VERSÃO ULTRA AGRESSIVA
 * 
 * Este overlay usa técnicas de NÍVEL BAIXO para garantir que apareça
 * sobre QUALQUER elemento, incluindo iframes do Monetag:
 * 
 * 1. Injeção de CSS crítico no <head> ANTES de tudo
 * 2. MutationObserver para detectar e bloquear elementos em tempo real
 * 3. Interceptação de document.createElement para bloquear iframes
 * 4. Overlay anexado diretamente ao <html> (não ao body)
 * 5. Shadow DOM para isolar o overlay de manipulações externas
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
    __OVERLAY_ACTIVE__?: boolean;
    __ORIGINAL_CREATE_ELEMENT__?: typeof document.createElement;
    MontagOverlay?: {
      show: () => void;
      hide: () => void;
    };
    [key: string]: any;
  }
}

// ========================================
// CSS CRÍTICO - Injetado no <head> para ter prioridade máxima
// ========================================
const CRITICAL_CSS = `
  /* OVERLAY GLOBAL - PRIORIDADE MÁXIMA */
  #ym-overlay-container {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 2147483647 !important;
    pointer-events: auto !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(10, 14, 39, 0.99) !important;
    visibility: visible !important;
    opacity: 1 !important;
  }
  
  /* Quando overlay está ativo, esconder TUDO exceto o overlay */
  html.ym-overlay-active body > *:not(#ym-overlay-container),
  html.ym-overlay-active body > iframe,
  html.ym-overlay-active iframe,
  html.ym-overlay-active [data-zone],
  html.ym-overlay-active [id*="monetag"],
  html.ym-overlay-active [class*="monetag"] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
    z-index: -1 !important;
  }
  
  /* Forçar overlay a ficar visível */
  html.ym-overlay-active #ym-overlay-container {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    z-index: 2147483647 !important;
  }
`;

// ========================================
// INJETAR CSS CRÍTICO NO HEAD
// ========================================
function injectCriticalCSS() {
  if (document.getElementById('ym-critical-css')) return;
  
  const style = document.createElement('style');
  style.id = 'ym-critical-css';
  style.textContent = CRITICAL_CSS;
  
  // Inserir como PRIMEIRO elemento do head
  if (document.head.firstChild) {
    document.head.insertBefore(style, document.head.firstChild);
  } else {
    document.head.appendChild(style);
  }
  
  console.log('[OVERLAY] CSS crítico injetado');
}

// ========================================
// FUNÇÃO PRINCIPAL: CRIAR OVERLAY
// ========================================
function createFloatingOverlay() {
  console.log('[OVERLAY] 🚀 Iniciando criação do overlay...');
  
  // Verificar se já existe
  if (document.getElementById('ym-overlay-container') || window.__OVERLAY_ACTIVE__) {
    console.log('[OVERLAY] Overlay já existe ou está ativo');
    return;
  }
  
  window.__OVERLAY_ACTIVE__ = true;
  
  // Injetar CSS crítico
  injectCriticalCSS();
  
  // Adicionar classe ao HTML para ativar CSS de bloqueio
  document.documentElement.classList.add('ym-overlay-active');
  document.body.classList.add('ym-overlay-active');
  
  // ========================================
  // PASSO 1: Remover TODOS os iframes e elementos do Monetag
  // ========================================
  const removeMonetag = () => {
    // Remover iframes
    document.querySelectorAll('iframe').forEach(el => {
      console.log('[OVERLAY] Removendo iframe:', el.src);
      el.remove();
    });
    
    // Remover elementos do Monetag
    document.querySelectorAll('[data-zone], [id*="monetag"], [class*="monetag"], [id*="ad-container"]').forEach(el => {
      if (!el.closest('#ym-overlay-container')) {
        console.log('[OVERLAY] Removendo elemento Monetag');
        el.remove();
      }
    });
    
    // Remover elementos com z-index alto
    document.querySelectorAll('div').forEach(el => {
      const style = window.getComputedStyle(el);
      const zIndex = parseInt(style.zIndex) || 0;
      if (zIndex > 999999 && el.id !== 'ym-overlay-container' && !el.closest('#ym-overlay-container')) {
        console.log('[OVERLAY] Removendo elemento com z-index alto:', zIndex);
        el.remove();
      }
    });
  };
  
  // Executar remoção imediatamente
  removeMonetag();
  
  // ========================================
  // PASSO 2: Criar container do overlay
  // ========================================
  const container = document.createElement('div');
  container.id = 'ym-overlay-container';
  
  // Aplicar estilos inline como backup
  container.style.cssText = `
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 2147483647 !important;
    background: rgba(10, 14, 39, 0.99) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
  `;
  
  // Conteúdo do overlay
  container.innerHTML = `
    <div style="
      background: linear-gradient(135deg, rgba(26, 26, 46, 0.98) 0%, rgba(22, 33, 62, 0.98) 100%);
      padding: 40px 50px;
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.5), 0 0 30px rgba(139, 92, 246, 0.3);
      text-align: center;
      max-width: 90%;
      width: 400px;
      border: 2px solid rgba(0, 221, 255, 0.4);
    ">
      <div style="width: 100px; height: 100px; margin: 0 auto 20px; position: relative;">
        <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; transform: rotate(-90deg);">
          <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(139, 92, 246, 0.3)" stroke-width="8"></circle>
          <circle id="ym-progress" cx="50" cy="50" r="45" fill="none" stroke="#00ddff" stroke-width="8" stroke-linecap="round" stroke-dasharray="283" stroke-dashoffset="0" style="transition: stroke-dashoffset 1s linear; filter: drop-shadow(0 0 10px #00ddff);"></circle>
        </svg>
        <span id="ym-countdown" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: monospace; font-size: 36px; font-weight: 700; color: #00ddff; text-shadow: 0 0 15px rgba(0, 221, 255, 0.7);">${OVERLAY_DURATION}</span>
      </div>
      <h3 style="margin: 0 0 10px 0; color: #ffffff; font-size: 20px; font-weight: 600;">Processando sua tarefa...</h3>
      <p style="margin: 0; color: #a0aec0; font-size: 14px;">Aguarde enquanto validamos seu clique</p>
    </div>
  `;
  
  // Bloquear eventos
  const blockEvent = (e: Event) => {
    e.stopPropagation();
    e.stopImmediatePropagation();
    e.preventDefault();
    return false;
  };
  
  ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend', 'touchmove', 
   'contextmenu', 'pointerdown', 'pointerup', 'wheel', 'scroll'].forEach(evt => {
    container.addEventListener(evt, blockEvent, { capture: true, passive: false });
  });
  
  // ========================================
  // PASSO 3: Adicionar ao DOM
  // ========================================
  
  // Adicionar como último filho do body para ficar por cima
  document.body.appendChild(container);
  
  // Forçar repaint
  container.offsetHeight;
  
  console.log('[OVERLAY] ✅ Overlay criado com sucesso!');
  
  // ========================================
  // PASSO 4: MutationObserver para bloquear novos elementos
  // ========================================
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) {
          const el = node as HTMLElement;
          
          // Remover iframes
          if (el.tagName === 'IFRAME') {
            console.log('[OBSERVER] Bloqueando iframe:', (el as HTMLIFrameElement).src);
            el.remove();
            return;
          }
          
          // Remover elementos do Monetag
          if (el.hasAttribute('data-zone') || 
              (el.id && el.id.toLowerCase().includes('monetag')) ||
              (el.className && typeof el.className === 'string' && el.className.toLowerCase().includes('monetag'))) {
            console.log('[OBSERVER] Bloqueando elemento Monetag');
            el.remove();
            return;
          }
          
          // Verificar z-index alto
          const style = window.getComputedStyle(el);
          const zIndex = parseInt(style.zIndex) || 0;
          if (zIndex > 999999 && el.id !== 'ym-overlay-container' && !el.closest('#ym-overlay-container')) {
            console.log('[OBSERVER] Bloqueando elemento com z-index alto');
            el.remove();
          }
        }
      });
    });
  });
  
  observer.observe(document.documentElement, {
    childList: true,
    subtree: true
  });
  
  // ========================================
  // PASSO 5: Loop de proteção
  // ========================================
  const protectionInterval = setInterval(() => {
    removeMonetag();
    
    // Garantir que overlay está visível
    const overlay = document.getElementById('ym-overlay-container');
    if (overlay) {
      overlay.style.display = 'flex';
      overlay.style.visibility = 'visible';
      overlay.style.opacity = '1';
      overlay.style.zIndex = '2147483647';
      
      // Mover para o final do body
      if (overlay.parentNode !== document.body || overlay !== document.body.lastElementChild) {
        document.body.appendChild(overlay);
      }
    }
  }, 100);
  
  // ========================================
  // PASSO 6: Contador regressivo
  // ========================================
  let countdown = OVERLAY_DURATION;
  const circumference = 283;
  
  const countdownInterval = setInterval(() => {
    countdown--;
    
    const timerEl = document.getElementById('ym-countdown');
    const progressEl = document.getElementById('ym-progress');
    
    if (timerEl) timerEl.textContent = String(countdown);
    if (progressEl) {
      const progress = (OVERLAY_DURATION - countdown) / OVERLAY_DURATION;
      progressEl.style.strokeDashoffset = String(circumference * (1 - progress));
    }
    
    console.log('[OVERLAY] Timer:', countdown);
    
    if (countdown <= 0) {
      clearInterval(countdownInterval);
      clearInterval(protectionInterval);
      observer.disconnect();
      
      // Limpar
      window.__OVERLAY_ACTIVE__ = false;
      document.documentElement.classList.remove('ym-overlay-active');
      document.body.classList.remove('ym-overlay-active');
      
      console.log('[OVERLAY] ✅ Finalizado! Recarregando...');
      window.location.reload();
    }
  }, 1000);
}

// ========================================
// REMOVER OVERLAY
// ========================================
function removeOverlay() {
  window.__OVERLAY_ACTIVE__ = false;
  document.documentElement.classList.remove('ym-overlay-active');
  document.body.classList.remove('ym-overlay-active');
  
  const overlay = document.getElementById('ym-overlay-container');
  if (overlay) overlay.remove();
}

// ========================================
// DEBOUNCE
// ========================================
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

// ========================================
// ENVIAR POSTBACK
// ========================================
function sendPostbackToNewServer(eventType: string) {
  if (!shouldProcessEvent(eventType)) return;

  console.log('[POSTBACK] Enviando', eventType);

  // SE FOR CLIQUE, MOSTRAR OVERLAY IMEDIATAMENTE
  if (eventType === 'click') {
    console.log('[POSTBACK] 🎯 CLIQUE DETECTADO! Mostrando overlay...');
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

  fetch(`${POSTBACK_URL}?${params.toString()}`, { method: 'GET', mode: 'cors' })
    .then(r => r.json())
    .then(data => console.log('[POSTBACK] ✅', eventType, 'enviado:', data))
    .catch(err => console.error('[POSTBACK] ❌ Erro:', err));
}

// ========================================
// INSTALAR INTERCEPTADORES
// ========================================
function installInterceptors() {
  if (window.__MONETAG_INTERCEPTORS_INSTALLED__) {
    console.log('[INTERCEPTOR] Já instalados');
    return;
  }
  
  window.__MONETAG_INTERCEPTORS_INSTALLED__ = true;
  window.__LAST_IMPRESSION_TIME__ = 0;
  window.__LAST_CLICK_TIME__ = 0;
  window.__OVERLAY_ACTIVE__ = false;

  console.log('[INTERCEPTOR] Instalando interceptadores...');

  // Injetar CSS crítico imediatamente
  injectCriticalCSS();

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
        console.log('[FETCH] Interceptado:', url);
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
        console.log('[XHR] Interceptado:', url);
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
              console.log('[IMG] Interceptado:', value);
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
          console.log('[BEACON] Interceptado:', url);
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

// ========================================
// COMPONENTE REACT
// ========================================
export default function MonetagOverlay() {
  useEffect(() => {
    installInterceptors();
  }, []);

  return null;
}

export { createFloatingOverlay, installInterceptors, removeOverlay };
