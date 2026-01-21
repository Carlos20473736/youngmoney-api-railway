import { useEffect } from 'react';

/*
 * SISTEMA GLOBAL DE OVERLAY MONETAG
 * 
 * CORREÇÕES IMPLEMENTADAS:
 * 1. Debounce para evitar detecção duplicada de impressões
 * 2. Detecção imediata de clique (sem esperar segundo clique)
 * 3. Overlay que REMOVE elementos do Monetag em vez de apenas esconder
 */

const YMID_STORAGE_KEY = 'youngmoney_ymid';
const EMAIL_STORAGE_KEY = 'youngmoney_email';
const POSTBACK_SERVER = 'https://monetag-postback-server-production.up.railway.app';
const POSTBACK_URL = `${POSTBACK_SERVER}/api/postback`;
const ZONE_ID = '10325249';

// Duração do overlay em segundos
const OVERLAY_DURATION = 15;

// Controle de debounce para evitar duplicação
const DEBOUNCE_TIME = 2000; // 2 segundos entre eventos do mesmo tipo

// Declaração global para o sistema de overlay
declare global {
  interface Window {
    __MONETAG_INTERCEPTORS_INSTALLED__?: boolean;
    __LAST_IMPRESSION_TIME__?: number;
    __LAST_CLICK_TIME__?: number;
    __IMPRESSION_COUNT__?: number;
    MontagOverlay?: {
      show: () => void;
      hide: () => void;
    };
    [key: string]: any;
  }
}

// Função para criar o overlay flutuante que REMOVE o Monetag
function createFloatingOverlay() {
  // Verificar se já existe um overlay
  if (document.getElementById('monetag-block-overlay')) {
    console.log('[OVERLAY] Overlay já existe, ignorando...');
    return;
  }

  console.log('[OVERLAY] 🎯 CLIQUE DETECTADO! Criando overlay flutuante de ' + OVERLAY_DURATION + ' segundos...');

  // ========================================
  // TÉCNICA AGRESSIVA: REMOVER todos os elementos do Monetag
  // ========================================
  
  // Remover TODOS os iframes
  document.querySelectorAll('iframe').forEach(iframe => {
    console.log('[OVERLAY] Removendo iframe:', iframe.src || iframe.id);
    iframe.remove();
  });
  
  // Remover elementos do Monetag
  document.querySelectorAll('[id*="monetag"], [id*="Monetag"], [class*="monetag"], [class*="Monetag"], [data-zone], [id*="ad-"], [class*="ad-container"]').forEach(el => {
    console.log('[OVERLAY] Removendo elemento Monetag:', el.id || el.className);
    el.remove();
  });

  // Remover scripts do Monetag para evitar recriação
  document.querySelectorAll('script[src*="monetag"], script[src*="libtl"]').forEach(script => {
    console.log('[OVERLAY] Removendo script:', (script as HTMLScriptElement).src);
    script.remove();
  });

  // ========================================
  // Criar estilo global
  // ========================================
  if (!document.getElementById('monetag-overlay-critical-styles')) {
    const criticalStyle = document.createElement('style');
    criticalStyle.id = 'monetag-overlay-critical-styles';
    criticalStyle.textContent = `
      #monetag-block-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 2147483647 !important;
        background: rgba(10, 14, 39, 0.98) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        pointer-events: all !important;
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
    document.head.appendChild(criticalStyle);
  }

  // ========================================
  // Criar overlay
  // ========================================
  const overlay = document.createElement('div');
  overlay.id = 'monetag-block-overlay';
  
  overlay.style.cssText = `
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 2147483647 !important;
    background: rgba(10, 14, 39, 0.98) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    pointer-events: all !important;
    animation: fadeInOverlay 0.3s ease !important;
  `;

  // Bloquear eventos
  const blockEvent = (e: Event) => {
    e.stopPropagation();
    e.stopImmediatePropagation();
    e.preventDefault();
    return false;
  };

  ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend', 'touchmove', 
   'contextmenu', 'pointerdown', 'pointerup', 'pointermove'].forEach(eventType => {
    overlay.addEventListener(eventType, blockEvent, { capture: true, passive: false });
  });

  // Mensagem do overlay
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
  document.documentElement.appendChild(overlay);

  // Continuar removendo elementos que possam aparecer
  const cleanupInterval = setInterval(() => {
    document.querySelectorAll('iframe').forEach(iframe => {
      if (!iframe.closest('#monetag-block-overlay')) {
        iframe.remove();
      }
    });
    document.querySelectorAll('[id*="monetag"], [id*="Monetag"], [class*="monetag"], [class*="Monetag"], [data-zone]').forEach(el => {
      if (el.id !== 'monetag-block-overlay' && !el.closest('#monetag-block-overlay')) {
        el.remove();
      }
    });
  }, 100);

  // Contador regressivo
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
      clearInterval(cleanupInterval);
      console.log('[OVERLAY] Contador finalizado! Reiniciando página...');
      window.location.reload();
    }
  }, 1000);

  console.log('[OVERLAY] ✅ Overlay criado e elementos Monetag removidos!');
}

// Função para remover o overlay
function removeOverlay() {
  const overlay = document.getElementById('monetag-block-overlay');
  if (overlay) {
    overlay.remove();
  }
}

// Função para verificar debounce
function shouldProcessEvent(eventType: string): boolean {
  const now = Date.now();
  
  if (eventType === 'impression') {
    const lastTime = window.__LAST_IMPRESSION_TIME__ || 0;
    if (now - lastTime < DEBOUNCE_TIME) {
      console.log('[DEBOUNCE] Impressão ignorada (muito rápido)');
      return false;
    }
    window.__LAST_IMPRESSION_TIME__ = now;
    return true;
  }
  
  if (eventType === 'click') {
    const lastTime = window.__LAST_CLICK_TIME__ || 0;
    if (now - lastTime < DEBOUNCE_TIME) {
      console.log('[DEBOUNCE] Clique ignorado (muito rápido)');
      return false;
    }
    window.__LAST_CLICK_TIME__ = now;
    return true;
  }
  
  return true;
}

// Função para enviar postback
function sendPostbackToNewServer(eventType: string) {
  // Verificar debounce para evitar duplicação
  if (!shouldProcessEvent(eventType)) {
    return;
  }

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

  // Inicializar contadores de debounce
  window.__LAST_IMPRESSION_TIME__ = 0;
  window.__LAST_CLICK_TIME__ = 0;

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

  // ========================================
  // 4. INTERCEPTAR createElement para pegar novos elementos
  // ========================================
  const originalCreateElement = document.createElement.bind(document);
  document.createElement = function(tagName: string, options?: ElementCreationOptions) {
    const element = originalCreateElement(tagName, options);
    
    if (tagName.toLowerCase() === 'img') {
      const originalSetAttribute = element.setAttribute.bind(element);
      element.setAttribute = function(name: string, value: string) {
        if (name === 'src' && typeof value === 'string' && value.includes('youngmoney-api-railway')) {
          if (value.includes('%7Bymid%7D') || value.includes('{ymid}')) {
            console.log('[BLOQUEIO IMG ATTR] 🚫 Postback bloqueado:', value);
            const eventType = value.includes('event_type=click') || value.includes('event_type%3Dclick') ? 'click' : 'impression';
            sendPostbackToNewServer(eventType);
            return;
          }
        }
        return originalSetAttribute(name, value);
      };
    }
    
    return element;
  };

  // ========================================
  // 5. INTERCEPTAR sendBeacon
  // ========================================
  if (navigator.sendBeacon) {
    const originalSendBeacon = navigator.sendBeacon.bind(navigator);
    navigator.sendBeacon = function(url: string, data?: BodyInit | null) {
      if (typeof url === 'string' && url.includes('youngmoney-api-railway')) {
        if (url.includes('%7Bymid%7D') || url.includes('{ymid}')) {
          console.log('[BLOQUEIO BEACON] 🚫 Postback bloqueado:', url);
          const eventType = url.includes('event_type=click') || url.includes('event_type%3Dclick') ? 'click' : 'impression';
          sendPostbackToNewServer(eventType);
          return true;
        }
      }
      return originalSendBeacon(url, data);
    };
  }

  console.log('[INTERCEPTOR] ✅ Interceptadores globais instalados (fetch, XHR, Image, createElement, sendBeacon)');
}

// Componente React que instala os interceptadores ao montar
export default function MonetagOverlay() {
  useEffect(() => {
    // Instalar interceptadores quando o componente montar
    installInterceptors();
  }, []);

  return null;
}

export { createFloatingOverlay, installInterceptors, removeOverlay };
