import { useEffect } from 'react';

/*
 * SISTEMA GLOBAL DE OVERLAY MONETAG - VERSÃO CORRIGIDA
 * 
 * Correções aplicadas:
 * 1. Debounce melhorado para evitar contagem dupla de impressões
 * 2. Interceptação de createElement para bloquear iframes ANTES de serem adicionados
 * 3. Overlay com Shadow DOM para isolamento total
 * 4. Sistema de bloqueio mais agressivo com pointer-events
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';
const EMAIL_STORAGE_KEY = 'youngmoney_email';
const POSTBACK_SERVER = 'https://monetag-postback-server-production.up.railway.app';
const POSTBACK_URL = `${POSTBACK_SERVER}/api/postback`;
const ZONE_ID = '10325249';

const OVERLAY_DURATION = 15;
// Aumentar debounce para 5 segundos para evitar contagem dupla
const DEBOUNCE_TIME = 5000;
// Tempo mínimo entre impressões do mesmo tipo
const MIN_IMPRESSION_INTERVAL = 10000;

declare global {
  interface Window {
    __MONETAG_INTERCEPTORS_INSTALLED__?: boolean;
    __LAST_IMPRESSION_TIME__?: number;
    __LAST_CLICK_TIME__?: number;
    __OVERLAY_ACTIVE__?: boolean;
    __ORIGINAL_CREATE_ELEMENT__?: typeof document.createElement;
    __BLOCKED_IFRAMES__?: Set<HTMLIFrameElement>;
    __IMPRESSION_COUNT__?: number;
    __CLICK_COUNT__?: number;
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
  
  /* Quando overlay está ativo, bloquear TODOS os iframes e elementos Monetag */
  html.ym-overlay-active iframe,
  html.ym-overlay-active [data-zone],
  html.ym-overlay-active [id*="monetag"],
  html.ym-overlay-active [class*="monetag"],
  html.ym-overlay-active [id*="ad-container"],
  html.ym-overlay-active div[style*="z-index: 2147483647"]:not(#ym-overlay-container),
  html.ym-overlay-active div[style*="z-index:2147483647"]:not(#ym-overlay-container) {
    pointer-events: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    z-index: -9999 !important;
    display: none !important;
  }
  
  /* Forçar overlay a ficar visível e interativo */
  html.ym-overlay-active #ym-overlay-container {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    z-index: 2147483647 !important;
    pointer-events: auto !important;
  }
  
  /* Bloquear cliques em tudo exceto o overlay */
  html.ym-overlay-active body {
    pointer-events: none !important;
  }
  
  html.ym-overlay-active #ym-overlay-container,
  html.ym-overlay-active #ym-overlay-container * {
    pointer-events: auto !important;
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
// BLOQUEAR IFRAME - Função para desabilitar iframe
// ========================================
function blockIframe(iframe: HTMLIFrameElement) {
  if (!iframe || iframe.closest('#ym-overlay-container')) return;
  
  // Adicionar ao conjunto de iframes bloqueados
  if (!window.__BLOCKED_IFRAMES__) {
    window.__BLOCKED_IFRAMES__ = new Set();
  }
  window.__BLOCKED_IFRAMES__.add(iframe);
  
  // Aplicar estilos de bloqueio
  iframe.style.cssText = `
    pointer-events: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    z-index: -9999 !important;
    display: none !important;
    position: absolute !important;
    left: -9999px !important;
    top: -9999px !important;
  `;
  
  // Remover src para parar carregamento
  iframe.src = 'about:blank';
  
  // Tentar remover do DOM
  try {
    iframe.remove();
  } catch (e) {
    console.log('[OVERLAY] Não foi possível remover iframe:', e);
  }
  
  console.log('[OVERLAY] Iframe bloqueado');
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
  // PASSO 1: Bloquear TODOS os iframes existentes
  // ========================================
  const blockAllIframes = () => {
    document.querySelectorAll('iframe').forEach((iframe) => {
      blockIframe(iframe as HTMLIFrameElement);
    });
    
    // Bloquear elementos do Monetag
    document.querySelectorAll('[data-zone], [id*="monetag"], [class*="monetag"], [id*="ad-container"]').forEach(el => {
      if (!el.closest('#ym-overlay-container')) {
        (el as HTMLElement).style.cssText = `
          pointer-events: none !important;
          visibility: hidden !important;
          opacity: 0 !important;
          z-index: -9999 !important;
          display: none !important;
        `;
      }
    });
    
    // Bloquear elementos com z-index alto
    document.querySelectorAll('div').forEach(el => {
      const style = window.getComputedStyle(el);
      const zIndex = parseInt(style.zIndex) || 0;
      if (zIndex > 999999 && el.id !== 'ym-overlay-container' && !el.closest('#ym-overlay-container')) {
        (el as HTMLElement).style.cssText = `
          pointer-events: none !important;
          visibility: hidden !important;
          opacity: 0 !important;
          z-index: -9999 !important;
          display: none !important;
        `;
      }
    });
  };
  
  // Executar bloqueio imediatamente
  blockAllIframes();
  
  // ========================================
  // PASSO 2: Criar container do overlay com Shadow DOM
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
  
  // Criar Shadow DOM para isolamento
  const shadow = container.attachShadow({ mode: 'closed' });
  
  // Estilos internos do Shadow DOM
  const innerStyle = document.createElement('style');
  innerStyle.textContent = `
    :host {
      all: initial;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 100% !important;
      height: 100% !important;
    }
    
    .overlay-content {
      background: linear-gradient(135deg, rgba(26, 26, 46, 0.98) 0%, rgba(22, 33, 62, 0.98) 100%);
      padding: 40px 50px;
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.5), 0 0 30px rgba(139, 92, 246, 0.3);
      text-align: center;
      max-width: 90%;
      width: 400px;
      border: 2px solid rgba(0, 221, 255, 0.4);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .timer-container {
      width: 100px;
      height: 100px;
      margin: 0 auto 20px;
      position: relative;
    }
    
    .timer-svg {
      width: 100%;
      height: 100%;
      transform: rotate(-90deg);
    }
    
    .timer-bg {
      fill: none;
      stroke: rgba(139, 92, 246, 0.3);
      stroke-width: 8;
    }
    
    .timer-progress {
      fill: none;
      stroke: #00ddff;
      stroke-width: 8;
      stroke-linecap: round;
      stroke-dasharray: 283;
      stroke-dashoffset: 0;
      transition: stroke-dashoffset 1s linear;
      filter: drop-shadow(0 0 10px #00ddff);
    }
    
    .timer-text {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-family: monospace;
      font-size: 36px;
      font-weight: 700;
      color: #00ddff;
      text-shadow: 0 0 15px rgba(0, 221, 255, 0.7);
    }
    
    .title {
      margin: 0 0 10px 0;
      color: #ffffff;
      font-size: 20px;
      font-weight: 600;
    }
    
    .subtitle {
      margin: 0;
      color: #a0aec0;
      font-size: 14px;
    }
  `;
  
  // Conteúdo do overlay
  const content = document.createElement('div');
  content.className = 'overlay-content';
  content.innerHTML = `
    <div class="timer-container">
      <svg class="timer-svg" viewBox="0 0 100 100">
        <circle class="timer-bg" cx="50" cy="50" r="45"></circle>
        <circle class="timer-progress" id="ym-progress-inner" cx="50" cy="50" r="45"></circle>
      </svg>
      <span class="timer-text" id="ym-countdown-inner">${OVERLAY_DURATION}</span>
    </div>
    <h3 class="title">Processando sua tarefa...</h3>
    <p class="subtitle">Aguarde enquanto validamos seu clique</p>
  `;
  
  shadow.appendChild(innerStyle);
  shadow.appendChild(content);
  
  // Bloquear eventos no container
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
          
          // Bloquear iframes
          if (el.tagName === 'IFRAME') {
            console.log('[OBSERVER] Bloqueando novo iframe');
            blockIframe(el as HTMLIFrameElement);
            return;
          }
          
          // Bloquear elementos do Monetag
          if (el.hasAttribute('data-zone') || 
              (el.id && el.id.toLowerCase().includes('monetag')) ||
              (el.className && typeof el.className === 'string' && el.className.toLowerCase().includes('monetag'))) {
            console.log('[OBSERVER] Bloqueando elemento Monetag');
            el.style.cssText = `
              pointer-events: none !important;
              visibility: hidden !important;
              opacity: 0 !important;
              z-index: -9999 !important;
              display: none !important;
            `;
            return;
          }
          
          // Verificar z-index alto
          setTimeout(() => {
            const style = window.getComputedStyle(el);
            const zIndex = parseInt(style.zIndex) || 0;
            if (zIndex > 999999 && el.id !== 'ym-overlay-container' && !el.closest('#ym-overlay-container')) {
              console.log('[OBSERVER] Bloqueando elemento com z-index alto:', zIndex);
              el.style.cssText = `
                pointer-events: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                z-index: -9999 !important;
                display: none !important;
              `;
            }
          }, 0);
        }
      });
    });
  });
  
  observer.observe(document.documentElement, {
    childList: true,
    subtree: true
  });
  
  // ========================================
  // PASSO 5: Loop de proteção agressivo
  // ========================================
  const protectionInterval = setInterval(() => {
    blockAllIframes();
    
    // Garantir que overlay está visível e no topo
    const overlay = document.getElementById('ym-overlay-container');
    if (overlay) {
      overlay.style.display = 'flex';
      overlay.style.visibility = 'visible';
      overlay.style.opacity = '1';
      overlay.style.zIndex = '2147483647';
      overlay.style.pointerEvents = 'auto';
      
      // Mover para o final do body para ficar por cima
      if (overlay.parentNode !== document.body || overlay !== document.body.lastElementChild) {
        document.body.appendChild(overlay);
      }
    }
    
    // Garantir classes ativas
    document.documentElement.classList.add('ym-overlay-active');
    document.body.classList.add('ym-overlay-active');
  }, 50); // Verificar a cada 50ms
  
  // ========================================
  // PASSO 6: Contador regressivo
  // ========================================
  let countdown = OVERLAY_DURATION;
  const circumference = 283;
  
  const countdownInterval = setInterval(() => {
    countdown--;
    
    const timerEl = shadow.getElementById('ym-countdown-inner');
    const progressEl = shadow.getElementById('ym-progress-inner');
    
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
// DEBOUNCE MELHORADO - Evita contagem dupla
// ========================================
function shouldProcessEvent(eventType: string): boolean {
  const now = Date.now();
  
  // Inicializar contadores se não existirem
  if (typeof window.__IMPRESSION_COUNT__ === 'undefined') {
    window.__IMPRESSION_COUNT__ = 0;
  }
  if (typeof window.__CLICK_COUNT__ === 'undefined') {
    window.__CLICK_COUNT__ = 0;
  }
  
  if (eventType === 'impression') {
    const lastTime = window.__LAST_IMPRESSION_TIME__ || 0;
    const timeSinceLastImpression = now - lastTime;
    
    // Usar intervalo mínimo mais longo para impressões
    if (timeSinceLastImpression < MIN_IMPRESSION_INTERVAL) {
      console.log('[DEBOUNCE] Impressão ignorada - muito recente:', timeSinceLastImpression, 'ms');
      return false;
    }
    
    window.__LAST_IMPRESSION_TIME__ = now;
    window.__IMPRESSION_COUNT__++;
    console.log('[DEBOUNCE] Impressão aceita #', window.__IMPRESSION_COUNT__);
    return true;
  }
  
  if (eventType === 'click') {
    const lastTime = window.__LAST_CLICK_TIME__ || 0;
    const timeSinceLastClick = now - lastTime;
    
    if (timeSinceLastClick < DEBOUNCE_TIME) {
      console.log('[DEBOUNCE] Clique ignorado - muito recente:', timeSinceLastClick, 'ms');
      return false;
    }
    
    window.__LAST_CLICK_TIME__ = now;
    window.__CLICK_COUNT__++;
    console.log('[DEBOUNCE] Clique aceito #', window.__CLICK_COUNT__);
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
  window.__IMPRESSION_COUNT__ = 0;
  window.__CLICK_COUNT__ = 0;
  window.__BLOCKED_IFRAMES__ = new Set();

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

  // 5. Interceptar createElement para bloquear iframes quando overlay ativo
  if (!window.__ORIGINAL_CREATE_ELEMENT__) {
    window.__ORIGINAL_CREATE_ELEMENT__ = document.createElement.bind(document);
    
    document.createElement = function(tagName: string, options?: ElementCreationOptions) {
      const element = window.__ORIGINAL_CREATE_ELEMENT__!(tagName, options);
      
      // Se for iframe e overlay estiver ativo, bloquear
      if (tagName.toLowerCase() === 'iframe' && window.__OVERLAY_ACTIVE__) {
        console.log('[CREATE_ELEMENT] Bloqueando criação de iframe durante overlay');
        
        // Interceptar quando o iframe for adicionado ao DOM
        const originalAppendChild = element.appendChild.bind(element);
        (element as any).appendChild = function(child: Node) {
          if (window.__OVERLAY_ACTIVE__) {
            console.log('[CREATE_ELEMENT] Bloqueando appendChild em iframe');
            return child;
          }
          return originalAppendChild(child);
        };
        
        // Marcar para bloqueio
        setTimeout(() => {
          if (window.__OVERLAY_ACTIVE__) {
            blockIframe(element as HTMLIFrameElement);
          }
        }, 0);
      }
      
      return element;
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
