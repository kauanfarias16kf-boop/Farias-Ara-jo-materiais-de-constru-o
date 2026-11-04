/* Farias Araújo · site público */
(() => {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
  const smoothstep = (p, e0, e1) => { const t = clamp((p - e0) / (e1 - e0), 0, 1); return t * t * (3 - 2 * t); };
  const reduz = matchMedia('(prefers-reduced-motion: reduce)');
  const ano = $('#ano'); if (ano) ano.textContent = new Date().getFullYear();

  /* ---------- divisão de texto (gerador com semente: igual em todo carregamento) ---------- */
  function rng(seed) { let s = seed >>> 0; return () => (s = (s * 1664525 + 1013904223) >>> 0) / 4294967296; }
  $$('.divide').forEach((el, n) => {
    const frase = el.textContent.trim();
    const r = rng(7 + n * 31);
    const banda = el.closest('.band');
    const spread = parseFloat(banda?.dataset.spread || '.45');
    const grade = banda?.classList.contains('fx-grid');
    const palavras = frase.split(/\s+/);
    const total = frase.replace(/\s/g, '').length;
    let ci = 0;
    el.textContent = '';
    const sr = document.createElement('span'); sr.className = 'sr'; sr.textContent = frase; el.appendChild(sr);
    const vis = document.createElement('span'); vis.setAttribute('aria-hidden', 'true');
    palavras.forEach((p, wi) => {
      const w = document.createElement('span'); w.className = 'w';
      w.style.setProperty('--th', (wi / palavras.length * .55).toFixed(3));
      [...p].forEach(ch => {
        const c = document.createElement('span'); c.className = 'c'; c.textContent = ch;
        if (grade) {
          c.style.setProperty('--th', (ci / total * spread + r() * .06).toFixed(3));
          c.style.setProperty('--jx', (-(18 + r() * 26)).toFixed(1) + 'px');
        }
        ci++; w.appendChild(c);
      });
      vis.appendChild(w);
      if (wi < palavras.length - 1) vis.appendChild(document.createTextNode(' '));
    });
    el.appendChild(vis);
  });

  /* ---------- navegação sólida depois do topo ---------- */
  const nav = $('.nav'); let navSolida = null;
  function atualizaNav() { const s = scrollY > 40; if (s !== navSolida) { navSolida = s; nav.classList.toggle('solida', s); } }

  /* ---------- entradas ---------- */
  const ioEntrada = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    e.target.classList.add('in');
    ioEntrada.unobserve(e.target);
    setTimeout(() => e.target.classList.add('entrou'), 1800);   // aposenta os atrasos de escalonamento
  }), { rootMargin: '0px 0px -12% 0px' });
  $$('.secao').forEach(s => ioEntrada.observe(s));

  /* ---------- HERO: motor de scroll ---------- */
  const GATES = [
    '(max-width: 720px)',
    '(orientation: portrait) and (max-width: 1024px)',
    '(orientation: portrait) and (pointer: coarse)',
    '(orientation: landscape) and (pointer: coarse) and (max-height: 560px)',
    '(prefers-reduced-motion: reduce)'
  ];
  const MQLS = GATES.map(q => matchMedia(q));
  const hero = $('.hero');
  const stage = $('.palco');
  const video = $('video', stage);
  const VIDEO_URL = stage.dataset.video || '';          // vazio até o vídeo aprovado chegar
  const VIDEO_BYTES = Number(stage.dataset.bytes || 0);
  const grupos = $$('.traco .dg').map(g => ({ el: g, s: +g.dataset.s, e: +g.dataset.e, d: -1 }));
  const bands = $$('.band').map((el, i, arr) => ({ el, a: +el.dataset.a, b: +el.dataset.b, ramp: +el.dataset.ramp || 0, first: i === 0, last: i === arr.length - 1, links: !!el.querySelector('a,button'), op: -1, k: -1, on: null }));
  let target = 0, shown = 0, rafId = null, lastTick = 0, heroOnScreen = true, scrubOn = false, loadK = 0, lastZoom = -1, lastP = -1;

  function heroProgress() {
    const total = hero.offsetHeight - innerHeight;
    if (total <= 0) return 0;
    return clamp(-hero.getBoundingClientRect().top / total, 0, 1);
  }

  function pinta(p) {
    for (const g of grupos) {
      const d = clamp((p - g.s) / (g.e - g.s), 0, 1);
      if (Math.abs(d - g.d) > .004 || (d !== g.d && (d === 0 || d === 1))) { g.d = d; g.el.style.setProperty('--d', d.toFixed(3)); }
    }
    const z = .9 + .1 * p;
    if (Math.abs(z - lastZoom) > .002) { lastZoom = z; stage.style.setProperty('--zoom', z.toFixed(3)); }
    if (Math.abs(p - lastP) > .004) { lastP = p; stage.style.setProperty('--p', p.toFixed(3)); }
  }

  function updateCaptions(p) {
    for (const b of bands) {
      const f = Math.min(.02, (b.b - b.a) / 3);
      const entra = b.first ? 1 : smoothstep(p, b.a, b.a + f);
      const sai = b.last ? 0 : smoothstep(p, b.b - f, b.b);
      let op = entra * (1 - sai);
      let k = clamp((p - b.a) / (b.ramp || Math.min(.025, (b.b - b.a) * .35)), 0, 1);
      if (b.first) { k = Math.max(k, loadK); if (p > b.b) op = 0; }
      if (Math.abs(op - b.op) > .008 || (op !== b.op && (op === 0 || op === 1))) { b.op = op; b.el.style.opacity = op.toFixed(3); }
      if (Math.abs(k - b.k) > .008 || (k !== b.k && (k === 0 || k === 1))) { b.k = k; b.el.style.setProperty('--k', k.toFixed(3)); }
      const on = op > .5;
      if (on !== b.on) { b.on = on; b.el.classList.toggle('ativa', on); if (b.links) b.el.toggleAttribute('inert', !on); }
    }
  }

  // trava de seek: nunca dois seeks voando ao mesmo tempo
  let seekBusy = false, pendingTime = null, videoPronto = false;
  function requestSeek(t) {
    if (!videoPronto || !video.duration) return;
    if (seekBusy) { pendingTime = t; return; }
    seekBusy = true;
    video.currentTime = t;
  }
  video.addEventListener('seeked', () => {
    seekBusy = false;
    if (pendingTime !== null) { const t = pendingTime; pendingTime = null; requestSeek(t); }
  });
  video.addEventListener('error', () => { seekBusy = false; pendingTime = null; });

  function tick(now) {
    const dt = Math.min(100, now - (lastTick || now));
    lastTick = now;
    const k = .16;
    shown += (target - shown) * (1 - Math.pow(1 - k, dt / 16.667));
    if (Math.abs(target - shown) < .0005) { shown = target; rafId = null; lastTick = 0; }
    else rafId = requestAnimationFrame(tick);
    requestSeek(shown * (video.duration || 0));
    pinta(shown);
    updateCaptions(shown);
  }
  function onScroll() {
    target = heroProgress();
    if (rafId === null && heroOnScreen) rafId = requestAnimationFrame(tick);
  }
  new IntersectionObserver(es => { heroOnScreen = es[0].isIntersecting; if (heroOnScreen && scrubOn) onScroll(); }).observe(hero);

  // rampa única de carregamento da faixa um, que passa o bastão para o scroll
  function rampaCarga() {
    const t0 = performance.now();
    (function passo(now) {
      loadK = clamp((now - t0) / 1300, 0, 1);
      updateCaptions(shown);
      if (loadK < 1) requestAnimationFrame(passo);
    })(t0);
  }

  // carregador do vídeo (Blob transmitido com anel): só roda quando existir um vídeo aprovado
  let heroIniciado = false;
  function initHeroOnce() {
    if (heroIniciado) return; heroIniciado = true;
    rampaCarga();
    if (!VIDEO_URL) return;
    const poster = stage.dataset.poster;
    const posterLayer = $('.poster', stage);
    stage.insertAdjacentHTML('beforeend', '<svg class="anel" viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="20" fill="none" stroke="rgba(236,231,221,.15)" stroke-width="3"/><circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="126" style="stroke-dashoffset:var(--ld,126)"/></svg>');
    const ring = $('.anel', stage);
    let started = false;
    const start = () => { if (started) return; started = true; loadHeroBlob(ring).catch(() => failVideo(ring)); };
    if (poster) {
      posterLayer.style.backgroundImage = `url('${poster}')`;
      const img = new Image(); img.onload = start; img.onerror = start; img.src = poster;
      setTimeout(start, 4000);
    } else start();
  }
  async function loadHeroBlob(ring) {
    const ctrl = new AbortController();
    let watchdog = setTimeout(() => ctrl.abort(), 20000);
    const res = await fetch(VIDEO_URL, { priority: 'low', signal: ctrl.signal });
    if (!res.ok) throw new Error('video ' + res.status);
    const total = Number(res.headers.get('Content-Length')) || VIDEO_BYTES || 1;
    const reader = res.body.getReader();
    const chunks = []; let got = 0, lastRing = 0;
    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      clearTimeout(watchdog); watchdog = setTimeout(() => ctrl.abort(), 20000);
      chunks.push(value); got += value.length;
      const frac = Math.min(1, got / total), now = performance.now();
      if (now - lastRing > 100 || frac === 1) { lastRing = now; ring.style.setProperty('--ld', Math.round(126 * (1 - frac))); }
    }
    clearTimeout(watchdog);
    ring.style.setProperty('--ld', 0);
    video.src = URL.createObjectURL(new Blob(chunks, { type: 'video/mp4' }));
    video.load();
    video.addEventListener('canplay', () => {
      videoPronto = true;
      stage.classList.add('video-pronto', 'com-video');
      requestSeek(heroProgress() * video.duration);
    }, { once: true });
  }
  function failVideo(ring) {
    ring?.remove();
    stage.classList.add('video-falhou');      // a casa em traço segue carregando a jornada
  }

  function enableScrub() {
    if (scrubOn) return; scrubOn = true;
    initHeroOnce();
    addEventListener('scroll', onScroll, { passive: true });
    bands.forEach(b => { b.op = -1; b.k = -1; b.on = null; });
    grupos.forEach(g => (g.d = -1)); lastZoom = lastP = -1;
    unpinFinalStates();
    shown = target = heroProgress();
    pinta(shown); updateCaptions(shown);
    onScroll();
  }
  function disableScrub() {
    if (!scrubOn) return; scrubOn = false;
    removeEventListener('scroll', onScroll);
    if (rafId !== null) { cancelAnimationFrame(rafId); rafId = null; }
    grupos.forEach(g => g.el.style.removeProperty('--d'));
    bands.forEach(b => { b.el.style.removeProperty('opacity'); b.el.removeAttribute('inert'); });
  }
  function applyHeroMode() { if (MQLS.some(m => m.matches)) disableScrub(); else enableScrub(); }
  MQLS.forEach(m => m.addEventListener('change', applyHeroMode));

  /* ---------- assinatura: o fio de prumo ---------- */
  const prumo = $('.prumo');
  const cotaEl = $('.cota', prumo);
  let ang = 0, angVel = 0, len = -1, lenAlvo = 0, lastY = scrollY, prumoRaf = null, prumoT = 0, cotaTxt = '';
  function docProg() { const t = document.documentElement.scrollHeight - innerHeight; return t > 0 ? clamp(scrollY / t, 0, 1) : 0; }
  function prumoTick(now) {
    const dt = Math.min(48, now - (prumoT || now)) / 16.667; prumoT = now;
    angVel += (-.06 * ang - .16 * angVel) * dt;
    ang += angVel * dt;
    len += (lenAlvo - len) * (1 - Math.pow(.86, dt));
    prumo.style.setProperty('--ang', ang.toFixed(2) + 'deg');
    prumo.style.setProperty('--len', len.toFixed(1) + 'px');
    if (Math.abs(ang) < .02 && Math.abs(angVel) < .02 && Math.abs(lenAlvo - len) < .4) {
      ang = angVel = 0; len = lenAlvo; prumo.style.setProperty('--ang', '0deg'); prumo.style.setProperty('--len', len.toFixed(1) + 'px');
      prumoRaf = null; prumoT = 0; return;
    }
    prumoRaf = requestAnimationFrame(prumoTick);
  }
  function prumoScroll() {
    const dy = scrollY - lastY; lastY = scrollY;
    lenAlvo = innerHeight * (.14 + .64 * docProg());
    if (len < 0) len = lenAlvo;
    if (reduz.matches) { prumo.style.setProperty('--len', lenAlvo.toFixed(1) + 'px'); return; }
    angVel += clamp(-dy * .012, -1.2, 1.2);
    if (prumoRaf === null) prumoRaf = requestAnimationFrame(prumoTick);
  }
  addEventListener('scroll', () => { prumoScroll(); atualizaNav(); }, { passive: true });
  addEventListener('resize', () => { prumoScroll(); if (scrubOn) onScroll(); });
  const ioCota = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    const t = e.target.dataset.cota || '00 · TOPO';
    if (t !== cotaTxt) { cotaTxt = t; cotaEl.textContent = t; }
  }), { rootMargin: '-48% 0px -48% 0px' });
  [hero, ...$$('.secao')].forEach(s => ioCota.observe(s));

  /* ---------- momento interativo: segure para aprumar ---------- */
  const bancada = $('#bancada-wrap');
  const botao = $('#segurar');
  const leitura = $('#leitura-v');
  const leituraBox = $('.leitura');
  const passos = $$('.passos li');
  const msg = $('#msg-feito');
  const DESVIO = 3.8, DURA = 2200;
  let seg = 0, segurando = false, segRaf = null, segT = 0, feito = false, lastLeitura = '';
  const easeOut = t => 1 - Math.pow(1 - t, 3);
  function aplicaSeg() {
    const desv = DESVIO * (1 - easeOut(seg));
    const queda = .18 + .82 * Math.min(1, seg * 1.7);
    bancada.style.setProperty('--desvio', desv.toFixed(3));
    bancada.style.setProperty('--queda', queda.toFixed(3));
    botao.style.setProperty('--seg', seg.toFixed(3));
    const txt = desv.toFixed(1).replace('.', ',') + '°';
    if (txt !== lastLeitura) { lastLeitura = txt; leitura.textContent = txt; }
  }
  function segTick(now) {
    const dt = Math.min(50, now - (segT || now)); segT = now;
    if (segurando) seg = Math.min(1, seg + dt / DURA);
    else seg = Math.max(0, seg - dt / (DURA * .9) * (0.35 + seg));   // desce suave, nunca estala
    aplicaSeg();
    if (seg >= 1 && !feito) { completa(); segRaf = null; segT = 0; return; }
    if (!segurando && seg <= 0) { segRaf = null; segT = 0; return; }
    segRaf = requestAnimationFrame(segTick);
  }
  function comecar(e) {
    if (feito) return;
    if (e) e.preventDefault();
    segurando = true;
    if (segRaf === null) segRaf = requestAnimationFrame(segTick);
  }
  function soltar() { segurando = false; if (!feito && segRaf === null && seg > 0) segRaf = requestAnimationFrame(segTick); }
  function completa(instantaneo) {
    feito = true; seg = 1; aplicaSeg();
    bancada.classList.add('feito');
    leituraBox.classList.add('ok');
    $('#segurar-txt').textContent = 'No prumo';
    botao.setAttribute('aria-disabled', 'true');
    msg.textContent = 'No prumo. É essa a régua de cada etapa da sua obra.';
    msg.parentElement.classList.add('feito');
    passos.forEach((li, i) => instantaneo ? li.classList.add('aceso') : setTimeout(() => li.classList.add('aceso'), 260 + i * 220));
  }
  function reseta() {
    feito = false; seg = 0; segurando = false; aplicaSeg();
    bancada.classList.remove('feito'); leituraBox.classList.remove('ok');
    $('#segurar-txt').textContent = 'Segure para aprumar';
    botao.removeAttribute('aria-disabled');
    msg.textContent = ''; msg.parentElement.classList.remove('feito');
    passos.forEach(li => li.classList.remove('aceso'));
  }
  botao.addEventListener('pointerdown', e => { botao.setPointerCapture?.(e.pointerId); comecar(e); });
  ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(t => botao.addEventListener(t, soltar));
  botao.addEventListener('contextmenu', e => e.preventDefault());
  botao.addEventListener('keydown', e => { if ((e.key === ' ' || e.key === 'Enter') && !e.repeat) comecar(e); else if (e.key === ' ' || e.key === 'Enter') e.preventDefault(); });
  botao.addEventListener('keyup', e => { if (e.key === ' ' || e.key === 'Enter') soltar(); });
  aplicaSeg();

  /* ---------- movimento reduzido ao vivo, nas duas direções ---------- */
  let pinado = false;
  function pinToFinalStates() {
    pinado = true;
    if (!feito) completa(true);
    $$('.secao').forEach(s => s.classList.add('in', 'entrou'));
    if (prumoRaf) { cancelAnimationFrame(prumoRaf); prumoRaf = null; }
    ang = angVel = 0; prumo.style.setProperty('--ang', '0deg');
  }
  function unpinFinalStates() {
    if (!pinado) return; pinado = false;
    reseta();
  }
  reduz.addEventListener('change', e => { if (e.matches) pinToFinalStates(); else applyHeroMode(); });

  /* ---------- galeria (lida do painel) ---------- */
  const galeria = $('#galeria'), vazia = $('#galeria-vazia');
  const dlg = $('#ver');
  let galeriaPedida = false;
  async function carregaGaleria() {
    if (galeriaPedida) return; galeriaPedida = true;
    try {
      const r = await fetch('api/galeria.php', { headers: { Accept: 'application/json' } });
      if (!r.ok) return;
      const { fotos } = await r.json();
      if (!fotos || !fotos.length) return;
      galeria.innerHTML = '';
      fotos.forEach(f => {
        const b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = '<figure style="margin:0;height:100%"><img loading="lazy" alt=""><figcaption><span></span></figcaption></figure>';
        const img = b.querySelector('img'); img.src = f.miniatura; img.alt = f.titulo + (f.cidade ? ', ' + f.cidade : '');
        const cap = b.querySelector('figcaption'); cap.querySelector('span').textContent = f.servico || 'Obra';
        cap.appendChild(document.createTextNode(f.titulo + (f.cidade ? ' · ' + f.cidade : '')));
        b.setAttribute('aria-label', 'Ampliar foto: ' + img.alt);
        b.addEventListener('click', () => {
          $('#ver-img').src = f.arquivo; $('#ver-img').alt = img.alt; $('#ver-txt').textContent = img.alt;
          dlg.showModal();
        });
        galeria.appendChild(b);
      });
      galeria.hidden = false; vazia.hidden = true;
    } catch { /* sem servidor: fica o convite do Instagram */ }
  }
  new IntersectionObserver((es, o) => { if (es[0].isIntersecting) { carregaGaleria(); o.disconnect(); } }, { rootMargin: '600px 0px' }).observe($('#obras'));
  $('#ver-fechar').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });

  /* ---------- formulário de orçamento ---------- */
  const form = $('#form-orc');
  const secOrc = $('#orcamento');
  const ZAP = '5561994408678';
  const digitos = s => (s || '').replace(/\D+/g, '');
  const regras = {
    nome: v => v.trim().length >= 2 ? '' : 'Escreva seu nome.',
    whatsapp: v => { const d = digitos(v); return d.length >= 10 && d.length <= 13 ? '' : 'Confira o número com DDD, por exemplo 61 9 0000-0000.'; }
  };
  function marca(campo, erro) {
    const box = campo.closest('.campo');
    box.classList.toggle('erro', !!erro);
    box.querySelector('.msg').textContent = erro;
    campo.setAttribute('aria-invalid', erro ? 'true' : 'false');
  }
  Object.keys(regras).forEach(n => {
    const c = form.elements[n];
    c.addEventListener('blur', () => { if (c.value) marca(c, regras[n](c.value)); });
    c.addEventListener('input', () => { if (c.closest('.campo').classList.contains('erro') && !regras[n](c.value)) marca(c, ''); });
  });
  function textoZap(fd) {
    const partes = [`Olá! Meu nome é ${fd.get('nome')}.`, `Quero um orçamento de ${String(fd.get('servico')).toLowerCase()}${fd.get('cidade') ? ' em ' + fd.get('cidade') : ''}.`];
    if (fd.get('metragem')) partes.push(`Tamanho: ${fd.get('metragem')} m².`);
    if (fd.get('prazo')) partes.push(`Quero começar: ${String(fd.get('prazo')).toLowerCase()}.`);
    if (fd.get('mensagem')) partes.push(String(fd.get('mensagem')));
    return partes.join(' ');
  }
  form.addEventListener('submit', async e => {
    e.preventDefault();
    let primeiroErro = null;
    Object.keys(regras).forEach(n => { const c = form.elements[n]; const er = regras[n](c.value); marca(c, er); if (er && !primeiroErro) primeiroErro = c; });
    if (primeiroErro) { primeiroErro.focus(); return; }
    const fd = new FormData(form);
    const link = `https://wa.me/${ZAP}?text=${encodeURIComponent(textoZap(fd))}`;
    const btn = $('#f-enviar'); btn.disabled = true; btn.textContent = 'Enviando...';
    $('#f-falha').hidden = true;
    try {
      const r = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
      const j = await r.json().catch(() => ({}));
      if (r.ok && j.ok) {
        const nome = String(fd.get('nome')).trim().split(/\s+/)[0];
        $('#s-titulo').textContent = `Recebido, ${nome}.`;
        $('#s-zap').href = link;
        secOrc.classList.add('enviado');
        $('.sucesso', secOrc).setAttribute('tabindex', '-1'); $('.sucesso', secOrc).focus();
        return;
      }
      if (r.status === 422 && j.erros) {
        Object.entries(j.erros).forEach(([n, m]) => form.elements[n] && marca(form.elements[n], m));
        throw 'campos';
      }
      throw new Error(j.erro || 'falhou');
    } catch (err) {
      if (err !== 'campos') { $('#f-falha-zap').href = link; $('#f-falha').hidden = false; }
    } finally { btn.disabled = false; btn.textContent = 'Pedir meu orçamento'; }
  });

  /* ---------- pausa tudo com a aba escondida ---------- */
  document.addEventListener('visibilitychange', () => document.body.classList.toggle('pausado', document.hidden));

  /* ---------- partida ---------- */
  atualizaNav();
  prumoScroll();
  applyHeroMode();
  if (reduz.matches) pinToFinalStates();
})();
